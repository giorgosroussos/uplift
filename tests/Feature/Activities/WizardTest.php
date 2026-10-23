<?php

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\ActivityStatus;
use App\Modules\Activities\Enums\SubmissionStatus;
use App\Modules\Activities\Events\ActivityPublished;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Activities\Support\Submission;
use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Activities\FakeQuizType;

/*
| UC-022: the activity wizard. Basics are autosaved on the activity, the
| settings and content steps by the activity's type (here a quiz-like test
| type standing in for the quiz of P3-T3); every saved step is restored;
| publishing needs every required field; grading settings lock once there
| are submissions. JSON errors are RFC 9457 problem details.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);
    FakeQuizType::reset();
    app(ActivityTypes::class)->register(FakeQuizType::class);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create();
    $this->admin = User::factory()->orgAdmin()->create();
    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);
});

function wizardUrl(string $path = ''): string
{
    return test()->tenantUrl(test()->acme, 'activities'.($path === '' ? '' : '/'.$path));
}

/**
 * @param  array<string, mixed>  $data
 */
function autosave(User $user, Activity $activity, string $step, array $data): TestResponse
{
    return test()->actingAs($user)->putJson(wizardUrl("{$activity->id}/wizard/{$step}"), $data);
}

function newQuiz(?User $teacher = null, ?CourseSpace $space = null): Activity
{
    $response = test()->actingAs($teacher ?? test()->teacher)->postJson(wizardUrl(), [
        'course_space_id' => ($space ?? test()->space)->id,
        'type' => 'quiz',
        'title' => 'Sorting quiz',
        'description' => 'Bubble sort and quicksort',
        'deadline_at' => now()->addWeek()->toIso8601String(),
    ])->assertCreated();

    return Activity::query()->findOrFail($response->json('id'));
}

/**
 * @return array<string, mixed>
 */
function quizSettings(string $mode = 'self_paced', bool $negativeMarking = false): array
{
    return ['mode' => $mode, 'max_attempts' => 2, 'negative_marking' => $negativeMarking];
}

/**
 * @return array{questions: list<array{type: string, prompt: string, correct: string|null}>}
 */
function quizQuestions(string $correct = 'b'): array
{
    return ['questions' => [
        ['type' => 'single_choice', 'prompt' => 'Which sort is stable?', 'correct' => $correct],
        ['type' => 'true_false', 'prompt' => 'Quicksort is O(n log n) on average.', 'correct' => 'true'],
    ]];
}

function assertProblem(TestResponse $response, int $status): TestResponse
{
    $response->assertStatus($status)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('status', $status)
        ->assertJsonStructure(['type', 'title', 'status', 'detail']);

    return $response;
}

describe('creating', function () {
    it('creates a draft of the chosen type in the teacher\'s course space on the first autosave', function () {
        $deadline = now()->addWeek()->setTimezone('Europe/Athens')->startOfMinute();

        $response = $this->actingAs($this->teacher)->postJson(wizardUrl(), [
            'course_space_id' => $this->space->id,
            'type' => 'quiz',
            'title' => 'Sorting quiz',
            'description' => null,
            'deadline_at' => $deadline->toIso8601String(),
        ])->assertCreated();

        $activity = Activity::query()->sole();

        expect($response->json('id'))->toBe($activity->id)
            ->and($response->json('edit_url'))->toBe($this->tenantUrl($this->acme, "activities/{$activity->id}/edit"))
            ->and($activity->status)->toBe(ActivityStatus::Draft)
            ->and($activity->type)->toBe(ActivityKind::Quiz)
            ->and($activity->course_space_id)->toBe($this->space->id)
            ->and($activity->organization_id)->toBe($this->acme->id)
            ->and($activity->is_group)->toBeFalse()
            ->and($activity->deadline_at?->equalTo($deadline))->toBeTrue()
            ->and($activity->deadline_at?->getOffset())->toBe(0);
    });

    it('shows the wizard of a new activity with the registered types and the teacher\'s active course spaces', function () {
        CourseSpace::factory()->for($this->teacher, 'teacher')->archived()->create(['title' => 'Old course']);
        CourseSpace::factory()->for($this->otherTeacher, 'teacher')->create(['title' => 'Not mine']);

        $this->actingAs($this->teacher)
            ->get(wizardUrl('create?course_space='.$this->space->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Activities/Wizard')
                ->where('activity', null)
                ->where('step', 'basics')
                ->where('types', ['quiz', 'poll', 'topic_selection', 'topic_proposal', 'slot_booking'])
                ->where('basics.course_space_id', $this->space->id)
                ->where('courseSpaces', [['id' => $this->space->id, 'title' => 'Algorithms']]));
    });

    it('answers invalid basics with RFC 9457 problem details and stores nothing', function () {
        $response = assertProblem($this->actingAs($this->teacher)->postJson(wizardUrl(), [
            'course_space_id' => $this->space->id,
            'type' => 'quiz',
            'title' => '',
        ]), 422);

        $response->assertJsonPath('type', 'urn:uplift:problem:validation')
            ->assertJsonValidationErrors(['title']);

        expect(Activity::query()->count())->toBe(0);
    });

    it('refuses a type that no module has registered', function () {
        // Every type is registered by now; an empty registry stands in for a missing module.
        $this->app->instance(ActivityTypes::class, new ActivityTypes($this->app));

        assertProblem($this->actingAs($this->teacher)->postJson(wizardUrl(), [
            'course_space_id' => $this->space->id,
            'type' => 'slot_booking',
            'title' => 'Exam slots',
        ]), 422)->assertJsonValidationErrors(['type']);

        expect(Activity::query()->count())->toBe(0);
    });

    it('refuses to create an activity in another teacher\'s course space', function () {
        $foreign = CourseSpace::factory()->for($this->otherTeacher, 'teacher')->create();

        assertProblem($this->actingAs($this->teacher)->postJson(wizardUrl(), [
            'course_space_id' => $foreign->id,
            'type' => 'quiz',
            'title' => 'Sneaky quiz',
        ]), 403);

        expect(Activity::query()->count())->toBe(0);
    });

    it('refuses to create an activity in an archived course space', function () {
        $this->space->transitionTo(CourseSpaceStatus::Archived);

        assertProblem($this->actingAs($this->teacher)->postJson(wizardUrl(), [
            'course_space_id' => $this->space->id,
            'type' => 'quiz',
            'title' => 'Late quiz',
        ]), 403);

        expect(Activity::query()->count())->toBe(0);
    });

    it('refuses a course space of another organisation', function () {
        $beta = $this->createOrganization(['subdomain' => 'beta']);
        $foreign = $this->asTenant($beta, fn () => CourseSpace::factory()->create());

        assertProblem($this->actingAs($this->teacher)->postJson(wizardUrl(), [
            'course_space_id' => $foreign->id,
            'type' => 'quiz',
            'title' => 'Sneaky quiz',
        ]), 422)->assertJsonValidationErrors(['course_space_id']);

        expect($this->asTenant($beta, fn () => Activity::query()->count()))->toBe(0);
    });

    it('does not let students or org admins open the wizard or create activities', function (string $who) {
        $user = $this->{$who};

        $this->actingAs($user)->get(wizardUrl('create'))->assertForbidden();
        assertProblem($this->actingAs($user)->postJson(wizardUrl(), [
            'course_space_id' => $this->space->id,
            'type' => 'quiz',
            'title' => 'Quiz',
        ]), 403);

        expect(Activity::query()->count())->toBe(0);
    })->with(['student', 'admin']);
});

describe('autosave and restore', function () {
    it('UC-022: Given a teacher who leaves the wizard at step 3, When they return, Then the steps they completed are restored', function () {
        $activity = newQuiz();
        autosave($this->teacher, $activity, 'settings', quizSettings())
            ->assertOk()
            ->assertJsonPath('saved', true)
            ->assertJsonPath('steps.0.complete', true)
            ->assertJsonPath('steps.1.complete', true)
            ->assertJsonPath('steps.2.complete', false);

        // The teacher reaches step 3 (content) and leaves without saving it.
        $this->actingAs($this->teacher)
            ->get(wizardUrl("{$activity->id}/edit"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Activities/Wizard')
                ->where('activity.id', $activity->id)
                ->where('activity.status', 'draft')
                ->where('step', 'content')
                ->where('steps', [
                    ['key' => 'basics', 'component' => null, 'complete' => true],
                    ['key' => 'settings', 'component' => 'Testing/QuizSettings', 'complete' => true],
                    ['key' => 'content', 'component' => 'Testing/QuizQuestions', 'complete' => false],
                    ['key' => 'review', 'component' => null, 'complete' => false],
                ])
                ->where('basics.title', 'Sorting quiz')
                ->where('basics.description', 'Bubble sort and quicksort')
                ->where('basics.type', 'quiz')
                ->where('basics.course_space_id', $this->space->id)
                ->where('basics.deadline_at', $activity->deadline_at?->toIso8601String())
                ->where('data.settings', quizSettings())
                ->where('data.content', null));
    });

    it('opens a completed wizard at the review step and any saved step on request', function () {
        $activity = newQuiz();
        autosave($this->teacher, $activity, 'settings', quizSettings())->assertOk();
        autosave($this->teacher, $activity, 'content', quizQuestions())->assertOk();

        $this->actingAs($this->teacher)->get(wizardUrl("{$activity->id}/edit"))
            ->assertInertia(fn (Assert $page) => $page->where('step', 'review')->where('data.content', quizQuestions()));

        $this->actingAs($this->teacher)->get(wizardUrl("{$activity->id}/edit?step=settings"))
            ->assertInertia(fn (Assert $page) => $page->where('step', 'settings'));
    });

    it('autosaves the basics on the activity', function () {
        $activity = newQuiz();

        autosave($this->teacher, $activity, 'basics', ['title' => 'Sorting quiz (v2)', 'description' => '', 'deadline_at' => null])
            ->assertOk();

        expect($activity->fresh())
            ->title->toBe('Sorting quiz (v2)')
            ->description->toBeNull()
            ->deadline_at->toBeNull();
    });

    it('does not change the course space or the type after the first save', function () {
        $activity = newQuiz();
        $otherSpace = CourseSpace::factory()->for($this->teacher, 'teacher')->create();

        autosave($this->teacher, $activity, 'basics', ['title' => 'Quiz', 'course_space_id' => $otherSpace->id, 'type' => 'poll'])
            ->assertOk();

        expect($activity->fresh())
            ->course_space_id->toBe($this->space->id)
            ->type->toBe(ActivityKind::Quiz);
    });

    it('answers invalid step data with RFC 9457 problem details and keeps what was saved', function () {
        $activity = newQuiz();
        autosave($this->teacher, $activity, 'settings', quizSettings())->assertOk();

        assertProblem(autosave($this->teacher, $activity, 'settings', ['mode' => 'turbo', 'max_attempts' => 0, 'negative_marking' => false]), 422)
            ->assertJsonPath('type', 'urn:uplift:problem:validation')
            ->assertJsonValidationErrors(['mode', 'max_attempts']);

        assertProblem(autosave($this->teacher, $activity, 'basics', ['title' => str_repeat('x', 256)]), 422)
            ->assertJsonValidationErrors(['title']);

        expect(FakeQuizType::$data[$activity->id]['settings'])->toBe(quizSettings())
            ->and($activity->fresh()?->title)->toBe('Sorting quiz');
    });

    it('has no review data to save', function () {
        $activity = newQuiz();

        $this->actingAs($this->teacher)->putJson(wizardUrl("{$activity->id}/wizard/review"), [])->assertNotFound();
    });

    it('refuses autosave by anyone but the owning teacher, as problem details, and changes nothing', function (string $who, int $status) {
        $activity = newQuiz();
        autosave($this->teacher, $activity, 'settings', quizSettings())->assertOk();

        assertProblem(autosave($this->{$who}, $activity, 'settings', quizSettings('live', true)), $status);
        assertProblem(autosave($this->{$who}, $activity, 'basics', ['title' => 'Hacked']), $status);

        expect(FakeQuizType::$data[$activity->id]['settings'])->toBe(quizSettings())
            ->and($activity->fresh()?->title)->toBe('Sorting quiz');
    })->with([
        'another teacher' => ['otherTeacher', 404],
        'a student' => ['student', 404],
        'an org admin' => ['admin', 404],
    ]);

    it('does not open the wizard of another teacher\'s activity', function () {
        $activity = newQuiz();

        $this->actingAs($this->otherTeacher)->get(wizardUrl("{$activity->id}/edit"))->assertNotFound();
        $activity->transitionTo(ActivityStatus::Published);
        $this->actingAs($this->otherTeacher)->get(wizardUrl("{$activity->id}/edit"))->assertForbidden();
    });

    it('does not find activities of another organisation', function () {
        $beta = $this->createOrganization(['subdomain' => 'beta']);
        $foreign = $this->asTenant($beta, fn () => Activity::factory()->create());

        assertProblem(autosave($this->teacher, $foreign, 'basics', ['title' => 'Hacked']), 404);
        expect($this->asTenant($beta, fn () => Activity::query()->find($foreign->id)?->title))->toBe($foreign->title);
    });

    it('does not edit a closed activity', function () {
        $activity = newQuiz();
        $activity->transitionTo(ActivityStatus::Published);
        $activity->transitionTo(ActivityStatus::Closed);

        assertProblem(autosave($this->teacher, $activity, 'basics', ['title' => 'Reopened']), 403);
        expect($activity->fresh()?->title)->toBe('Sorting quiz');
    });
});

describe('publishing', function () {
    it('publishes a complete draft and announces it after commit', function () {
        Event::fake([ActivityPublished::class]);
        $activity = newQuiz();
        autosave($this->teacher, $activity, 'settings', quizSettings())->assertOk();
        autosave($this->teacher, $activity, 'content', quizQuestions())->assertOk();

        $this->actingAs($this->teacher)
            ->post(wizardUrl("{$activity->id}/publish"))
            ->assertRedirect($this->tenantUrl($this->acme, "activities/{$activity->id}"))
            ->assertSessionHasNoErrors();

        expect($activity->fresh())
            ->status->toBe(ActivityStatus::Published)
            ->published_at->not->toBeNull();

        Event::assertDispatchedTimes(ActivityPublished::class, 1);
        Event::assertDispatched(ActivityPublished::class, fn (ActivityPublished $event): bool => $event->activityId === $activity->id
            && $event->courseSpaceId === $this->space->id
            && $event->organizationId === $this->acme->id
            && $event->actorId === $this->teacher->id);
    });

    it('UC-022: Given a quiz with no questions, When the teacher publishes it, Then publishing is refused', function () {
        Event::fake([ActivityPublished::class]);
        $activity = newQuiz();
        autosave($this->teacher, $activity, 'settings', quizSettings())->assertOk();
        autosave($this->teacher, $activity, 'content', ['questions' => []])->assertOk();

        $this->actingAs($this->teacher)
            ->from(wizardUrl("{$activity->id}/edit?step=review"))
            ->post(wizardUrl("{$activity->id}/publish"))
            ->assertRedirect(wizardUrl("{$activity->id}/edit?step=review"))
            ->assertSessionHasErrors(['questions']);

        expect($activity->fresh())
            ->status->toBe(ActivityStatus::Draft)
            ->published_at->toBeNull();
        Event::assertNotDispatched(ActivityPublished::class);
    });

    it('refuses to publish while a required step is missing or the deadline has passed', function () {
        $activity = newQuiz();

        $this->actingAs($this->teacher)->post(wizardUrl("{$activity->id}/publish"))
            ->assertSessionHasErrors(['settings', 'questions']);

        autosave($this->teacher, $activity, 'settings', quizSettings())->assertOk();
        autosave($this->teacher, $activity, 'content', quizQuestions())->assertOk();
        $activity->forceFill(['deadline_at' => now()->subMinute()])->save();

        $this->actingAs($this->teacher)->post(wizardUrl("{$activity->id}/publish"))
            ->assertSessionHasErrors(['deadline_at']);

        expect($activity->fresh()?->status)->toBe(ActivityStatus::Draft);
    });

    it('UC-022: Given a live quiz, When the teacher adds a free-text question, Then it is refused', function () {
        $activity = newQuiz();
        autosave($this->teacher, $activity, 'settings', quizSettings('live'))->assertOk();
        autosave($this->teacher, $activity, 'content', quizQuestions())->assertOk();

        $withFreeText = quizQuestions();
        $withFreeText['questions'][] = ['type' => 'free_text', 'prompt' => 'Explain merge sort.', 'correct' => null];

        assertProblem(autosave($this->teacher, $activity, 'content', $withFreeText), 422)
            ->assertJsonValidationErrors(['questions.2.type']);

        expect(FakeQuizType::$data[$activity->id]['content'])->toBe(quizQuestions());
    });

    it('lets only the owning teacher publish, only a draft', function () {
        $activity = newQuiz();
        autosave($this->teacher, $activity, 'settings', quizSettings())->assertOk();
        autosave($this->teacher, $activity, 'content', quizQuestions())->assertOk();

        $this->actingAs($this->otherTeacher)->post(wizardUrl("{$activity->id}/publish"))->assertNotFound();
        $this->actingAs($this->student)->post(wizardUrl("{$activity->id}/publish"))->assertNotFound();
        expect($activity->fresh()?->status)->toBe(ActivityStatus::Draft);

        $this->actingAs($this->teacher)->post(wizardUrl("{$activity->id}/publish"))->assertRedirect();
        $this->actingAs($this->teacher)->post(wizardUrl("{$activity->id}/publish"))->assertForbidden();
        expect($activity->fresh()?->status)->toBe(ActivityStatus::Published);
    });

    it('closes a published activity, and nothing else', function () {
        $activity = newQuiz();

        $this->actingAs($this->teacher)->post(wizardUrl("{$activity->id}/close"))->assertForbidden();
        expect($activity->fresh()?->status)->toBe(ActivityStatus::Draft);

        $activity->transitionTo(ActivityStatus::Published);
        $this->actingAs($this->otherTeacher)->post(wizardUrl("{$activity->id}/close"))->assertForbidden();
        $this->actingAs($this->teacher)->post(wizardUrl("{$activity->id}/close"))
            ->assertRedirect($this->tenantUrl($this->acme, "activities/{$activity->id}"));
        expect($activity->fresh()?->status)->toBe(ActivityStatus::Closed);

        $this->actingAs($this->teacher)->post(wizardUrl("{$activity->id}/close"))->assertForbidden();
        expect($activity->fresh()?->status)->toBe(ActivityStatus::Closed);
    });
});

describe('grading lock', function () {
    beforeEach(function () {
        $this->quiz = newQuiz();
        autosave($this->teacher, $this->quiz, 'settings', quizSettings())->assertOk();
        autosave($this->teacher, $this->quiz, 'content', quizQuestions('b'))->assertOk();
        $this->actingAs($this->teacher)->post(wizardUrl("{$this->quiz->id}/publish"))->assertRedirect();
        FakeQuizType::$submissions[$this->quiz->id] = [
            new Submission('attempt-1', 'Nikos', now(), SubmissionStatus::Graded, '2', '2'),
        ];
    });

    it('UC-022: Given a published quiz with 1 submission, When the teacher changes a question\'s correct answer, Then it is refused', function () {
        assertProblem(autosave($this->teacher, $this->quiz, 'content', quizQuestions('c')), 409)
            ->assertJsonPath('type', 'urn:uplift:problem:grading-locked');

        expect(FakeQuizType::$data[$this->quiz->id]['content'])->toBe(quizQuestions('b'));
    });

    it('refuses any other setting that changes grading', function () {
        assertProblem(autosave($this->teacher, $this->quiz, 'settings', quizSettings('self_paced', true)), 409);

        expect(FakeQuizType::$data[$this->quiz->id]['settings'])->toBe(quizSettings());
    });

    it('still saves changes that do not change grading', function () {
        $reworded = quizQuestions('b');
        $reworded['questions'][0]['prompt'] = 'Which of these sorts is stable?';

        autosave($this->teacher, $this->quiz, 'content', $reworded)->assertOk();
        autosave($this->teacher, $this->quiz, 'basics', ['title' => 'Sorting quiz', 'deadline_at' => now()->addMonth()->toIso8601String()])->assertOk();

        expect(FakeQuizType::$data[$this->quiz->id]['content'])->toBe($reworded);
    });

    it('shows the wizard as grading-locked', function () {
        $this->actingAs($this->teacher)->get(wizardUrl("{$this->quiz->id}/edit"))
            ->assertInertia(fn (Assert $page) => $page->where('gradingLocked', true)->where('activity.status', 'published'));
    });

    it('lets the teacher change correct answers while there are no submissions', function () {
        FakeQuizType::$submissions = [];

        autosave($this->teacher, $this->quiz, 'content', quizQuestions('c'))->assertOk();

        expect(FakeQuizType::$data[$this->quiz->id]['content'])->toBe(quizQuestions('c'));
    });
});

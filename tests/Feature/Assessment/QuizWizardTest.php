<?php

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\ActivityStatus;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Assessment\Enums\QuestionType;
use App\Modules\Assessment\Enums\QuizMode;
use App\Modules\Assessment\Models\ChoiceOption;
use App\Modules\Assessment\Models\MatchingPair;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Assessment\Support\QuizType;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-022 with the real quiz type (P3-T3): the quiz registers through the
| ActivityType contract; its settings and questions are autosaved by the
| wizard and restored; questions are the quiz's own copies (DATA-008),
| validated per DATA-016 and ordered; publishing needs questions; grading
| locks once the quiz has any attempt (SCOPE-023); OWN-016 holds for every question a
| teacher can address through the wizard.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create();
    $this->admin = User::factory()->orgAdmin()->create();
    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();
});

function qwUrl(string $path = ''): string
{
    return test()->tenantUrl(test()->acme, ltrim($path, '/'));
}

/**
 * @param  array<string, mixed>  $data
 */
function qwSave(User $user, Activity $activity, string $step, array $data): TestResponse
{
    return test()->actingAs($user)->putJson(qwUrl("activities/{$activity->id}/wizard/{$step}"), $data);
}

function qwNewQuiz(): Activity
{
    $response = test()->actingAs(test()->teacher)->postJson(qwUrl('activities'), [
        'course_space_id' => test()->space->id,
        'type' => 'quiz',
        'title' => 'Sorting quiz',
        'description' => null,
        'deadline_at' => now()->addWeek()->toIso8601String(),
    ])->assertCreated();

    return Activity::query()->findOrFail($response->json('id'));
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function qwSettings(array $overrides = []): array
{
    return [
        'mode' => 'self_paced',
        'time_limit_minutes' => 30,
        'max_attempts' => 2,
        'negative_marking' => true,
        'negative_mark_fraction' => '25',
        'pass_mark_percentage' => '50.5',
        'score_visibility' => 'immediate',
        'answers_visibility' => 'after_deadline',
        ...$overrides,
    ];
}

/**
 * A single-choice question with fixed ids; $correct is the index of the
 * correct option.
 *
 * @return array<string, mixed>
 */
function qwChoice(string $id, string $prompt = 'Which sort is stable?', int $correct = 0, array $optionIds = []): array
{
    $optionIds = $optionIds ?: [(string) Str::uuid(), (string) Str::uuid()];

    return [
        'id' => $id,
        'type' => 'single_choice',
        'prompt' => $prompt,
        'points' => '2',
        'options' => [
            ['id' => $optionIds[0], 'text' => 'Merge sort', 'is_correct' => $correct === 0],
            ['id' => $optionIds[1], 'text' => 'Quicksort', 'is_correct' => $correct === 1],
        ],
        'pairs' => [],
    ];
}

/**
 * @return array<string, mixed>
 */
function qwFreeText(string $id): array
{
    return ['id' => $id, 'type' => 'free_text', 'prompt' => 'Explain merge sort.', 'points' => '5', 'options' => [], 'pairs' => []];
}

/**
 * @return array<string, mixed>
 */
function qwMatching(string $id): array
{
    return [
        'id' => $id,
        'type' => 'matching',
        'prompt' => 'Match the complexities.',
        'points' => '3.25',
        'options' => [],
        'pairs' => [
            ['id' => (string) Str::uuid(), 'left_text' => 'Merge sort', 'right_text' => 'n log n'],
            ['id' => (string) Str::uuid(), 'left_text' => 'Bubble sort', 'right_text' => 'n^2'],
        ],
    ];
}

/**
 * Everything stored about questions, to show that nothing changed.
 *
 * @return array<string, mixed>
 */
function qwSnapshot(): array
{
    return [
        'quizzes' => Quiz::query()->withoutGlobalScopes()->orderBy('activity_id')->get()->toArray(),
        'placements' => QuizQuestion::query()->withoutGlobalScopes()->orderBy('id')->get(['id', 'quiz_id', 'question_id', 'position'])->toArray(),
        'questions' => Question::query()->withoutGlobalScopes()->orderBy('id')->get(['id', 'owner_id', 'type', 'prompt', 'points', 'in_bank'])->toArray(),
        'options' => ChoiceOption::query()->withoutGlobalScopes()->orderBy('id')->get(['id', 'question_id', 'text', 'is_correct', 'position'])->toArray(),
        'pairs' => MatchingPair::query()->withoutGlobalScopes()->orderBy('id')->get(['id', 'question_id', 'left_text', 'right_text', 'position'])->toArray(),
    ];
}

/**
 * @return list<string>
 */
function qwPrompts(Activity $activity): array
{
    return QuizQuestion::query()->where('quiz_id', $activity->id)->orderBy('position')->with('question')->get()
        ->map(fn (QuizQuestion $placement): string => $placement->question->prompt)->all();
}

describe('the quiz activity type', function () {
    it('is registered through the ActivityType contract and offered by the wizard', function () {
        expect(app(ActivityTypes::class)->get(ActivityKind::Quiz))->toBeInstanceOf(QuizType::class);

        $this->actingAs($this->teacher)
            ->get(qwUrl('activities/create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('types', ['quiz', 'poll', 'topic_selection', 'topic_proposal']));

        $activity = qwNewQuiz();

        $this->actingAs($this->teacher)
            ->get(qwUrl("activities/{$activity->id}/edit"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Activities/Wizard')
                ->where('steps.1', ['key' => 'settings', 'component' => 'Assessment/Quiz/Settings', 'complete' => false])
                ->where('steps.2', ['key' => 'content', 'component' => 'Assessment/Quiz/Questions', 'complete' => false])
                ->where('step', 'settings'));
    });

    it('ships Vue pages for its wizard steps', function () {
        expect(resource_path('js/pages/Assessment/Quiz/Settings.vue'))->toBeFile()
            ->and(resource_path('js/pages/Assessment/Quiz/Questions.vue'))->toBeFile();
    });
});

describe('settings', function () {
    it('stores the quiz settings as the quiz subtype of the activity', function () {
        $activity = qwNewQuiz();

        qwSave($this->teacher, $activity, 'settings', qwSettings())->assertOk();

        $quiz = Quiz::query()->sole();
        expect($quiz->activity_id)->toBe($activity->id)
            ->and($quiz->organization_id)->toBe($this->acme->id)
            ->and($quiz->mode)->toBe(QuizMode::SelfPaced)
            ->and($quiz->time_limit)->toBe(1800)
            ->and($quiz->max_attempts)->toBe(2)
            ->and($quiz->negative_marking)->toBeTrue()
            ->and($quiz->negative_mark_fraction)->toBe('25.00')
            ->and($quiz->pass_mark_percentage)->toBe('50.50');
    });

    it('offers only self-paced quizzes in this phase', function () {
        $activity = qwNewQuiz();

        qwSave($this->teacher, $activity, 'settings', qwSettings(['mode' => 'live']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mode']);

        expect(Quiz::query()->count())->toBe(0);
    });

    it('refuses invalid settings with translated messages and stores nothing', function () {
        $activity = qwNewQuiz();

        qwSave($this->teacher, $activity, 'settings', qwSettings([
            'max_attempts' => 0,
            'negative_mark_fraction' => null,
            'pass_mark_percentage' => '101',
            'time_limit_minutes' => 0,
            'score_visibility' => 'sometimes',
        ]))
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonValidationErrors(['max_attempts', 'negative_mark_fraction', 'pass_mark_percentage', 'time_limit_minutes', 'score_visibility'])
            ->assertJsonPath('errors.max_attempts.0', fn (string $message) => str_contains($message, 'Attempts per student'));

        expect(Quiz::query()->count())->toBe(0);
    });

    it('drops the deduction when negative marking is off', function () {
        $activity = qwNewQuiz();

        qwSave($this->teacher, $activity, 'settings', qwSettings(['negative_marking' => false, 'negative_mark_fraction' => '30']))->assertOk();

        expect(Quiz::query()->sole()->negative_mark_fraction)->toBeNull();
    });
});

describe('UC-022: Given a new quiz in the wizard, Then it has 1 attempt, score visible immediately, answers visible after the deadline, no time limit, no negative marking and no pass mark', function () {
    it('opens a new quiz with no saved settings, so the wizard starts from its defaults (resources/js/lib/quizForm.test.ts)', function () {
        $activity = qwNewQuiz();

        $this->actingAs($this->teacher)
            ->get(qwUrl("activities/{$activity->id}/edit"))
            ->assertInertia(fn (Assert $page) => $page->where('data.settings', null));

        expect(Quiz::query()->count())->toBe(0);
    });

    it('stores the defaults as sent by the wizard', function () {
        $activity = qwNewQuiz();

        qwSave($this->teacher, $activity, 'settings', [
            'mode' => 'self_paced',
            'time_limit_minutes' => null,
            'max_attempts' => 1,
            'negative_marking' => false,
            'negative_mark_fraction' => null,
            'pass_mark_percentage' => null,
            'score_visibility' => 'immediate',
            'answers_visibility' => 'after_deadline',
        ])->assertOk();

        $quiz = Quiz::query()->sole();
        expect($quiz->max_attempts)->toBe(1)
            ->and($quiz->time_limit)->toBeNull()
            ->and($quiz->negative_marking)->toBeFalse()
            ->and($quiz->pass_mark_percentage)->toBeNull()
            ->and($quiz->score_visibility->value)->toBe('immediate')
            ->and($quiz->answers_visibility->value)->toBe('after_deadline');
    });
});

describe('UC-022: Given a time limit of 0 or 1441 minutes or max_attempts of 0 or 101, When saved, Then it is refused', function () {
    it('refuses out-of-range values and stores nothing', function (string $field, int $value) {
        $activity = qwNewQuiz();

        qwSave($this->teacher, $activity, 'settings', qwSettings([$field => $value]))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field]);

        expect(Quiz::query()->count())->toBe(0);
    })->with([
        'time limit 0' => ['time_limit_minutes', 0],
        'time limit 1441' => ['time_limit_minutes', 1441],
        'attempts 0' => ['max_attempts', 0],
        'attempts 101' => ['max_attempts', 101],
    ]);

    it('accepts the limits of the ranges', function (int $minutes, int $attempts) {
        $activity = qwNewQuiz();

        qwSave($this->teacher, $activity, 'settings', qwSettings(['time_limit_minutes' => $minutes, 'max_attempts' => $attempts]))->assertOk();

        expect(Quiz::query()->sole()->time_limit)->toBe($minutes * 60)
            ->and(Quiz::query()->sole()->max_attempts)->toBe($attempts);
    })->with([
        'lower' => [1, 1],
        'upper' => [1440, 100],
    ]);
});

describe('questions', function () {
    beforeEach(function () {
        $this->activity = qwNewQuiz();
        qwSave($this->teacher, $this->activity, 'settings', qwSettings())->assertOk();
    });

    it('stores the questions in order as quiz copies owned by the teacher', function () {
        [$a, $b, $c] = [(string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid()];

        qwSave($this->teacher, $this->activity, 'content', ['questions' => [
            qwChoice($a), qwMatching($b), qwFreeText($c),
        ]])->assertOk();

        expect(qwPrompts($this->activity))->toBe(['Which sort is stable?', 'Match the complexities.', 'Explain merge sort.']);

        $questions = Question::query()->whereKey([$a, $b, $c])->get();
        expect($questions)->toHaveCount(3)
            ->and($questions->every(fn (Question $q) => $q->in_bank === false && $q->owner_id === $this->teacher->id))->toBeTrue()
            ->and(Question::query()->find($b)?->pairs)->toHaveCount(2)
            ->and(Question::query()->find($b)?->points)->toBe('3.25');
    });

    it('reorders, edits and removes questions without creating new rows for the same ids', function () {
        [$a, $b, $c] = [(string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid()];
        $optionIds = [(string) Str::uuid(), (string) Str::uuid()];

        qwSave($this->teacher, $this->activity, 'content', ['questions' => [qwChoice($a, 'First', 0, $optionIds), qwChoice($b, 'Second'), qwChoice($c, 'Third')]])->assertOk();
        // Saving the same list again changes nothing.
        qwSave($this->teacher, $this->activity, 'content', ['questions' => [qwChoice($a, 'First', 0, $optionIds), qwChoice($b, 'Second'), qwChoice($c, 'Third')]])->assertOk();
        expect(Question::query()->count())->toBe(3);

        qwSave($this->teacher, $this->activity, 'content', ['questions' => [qwChoice($c, 'Third'), qwChoice($a, 'First, edited', 1, $optionIds)]])->assertOk();

        expect(qwPrompts($this->activity))->toBe(['Third', 'First, edited'])
            ->and(Question::query()->find($b))->toBeNull()
            ->and(Question::query()->findOrFail($a)->options->pluck('id')->all())->toBe($optionIds)
            ->and(Question::query()->findOrFail($a)->options->pluck('is_correct')->all())->toBe([false, true]);
    });

    it('validates every question per DATA-016 and stores nothing when one is invalid', function () {
        $before = qwSnapshot();
        $twoCorrect = qwChoice((string) Str::uuid());
        $twoCorrect['options'][1]['is_correct'] = true;
        $points = qwFreeText((string) Str::uuid());
        $points['points'] = '0.555';

        qwSave($this->teacher, $this->activity, 'content', ['questions' => [qwChoice((string) Str::uuid()), $twoCorrect, $points]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['questions.1.options', 'questions.2.points'])
            ->assertJsonPath('errors', fn (array $errors) => ! array_key_exists('questions.0.options', $errors));

        expect(qwSnapshot())->toBe($before);
    });

    it('needs the settings first', function () {
        $activity = qwNewQuiz();

        qwSave($this->teacher, $activity, 'content', ['questions' => [qwChoice((string) Str::uuid())]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['questions']);

        expect(Question::query()->count())->toBe(0);
    });
});

describe('UC-022: Given a quiz with no questions, When the teacher publishes it, Then publishing is refused', function () {
    it('refuses to publish without questions and keeps the draft', function () {
        $activity = qwNewQuiz();
        qwSave($this->teacher, $activity, 'settings', qwSettings())->assertOk();
        qwSave($this->teacher, $activity, 'content', ['questions' => []])->assertOk();

        $this->actingAs($this->teacher)
            ->from(qwUrl("activities/{$activity->id}/edit"))
            ->post(qwUrl("activities/{$activity->id}/publish"))
            ->assertSessionHasErrors(['questions']);

        expect($activity->refresh()->status)->toBe(ActivityStatus::Draft);
    });

    it('refuses to publish without settings', function () {
        $activity = qwNewQuiz();

        $this->actingAs($this->teacher)
            ->post(qwUrl("activities/{$activity->id}/publish"))
            ->assertSessionHasErrors(['settings', 'questions']);

        expect($activity->refresh()->status)->toBe(ActivityStatus::Draft);
    });

    it('publishes once the quiz has settings and a question', function () {
        $activity = qwNewQuiz();
        qwSave($this->teacher, $activity, 'settings', qwSettings())->assertOk();
        qwSave($this->teacher, $activity, 'content', ['questions' => [qwChoice((string) Str::uuid())]])->assertOk();

        $this->actingAs($this->teacher)
            ->post(qwUrl("activities/{$activity->id}/publish"))
            ->assertSessionHasNoErrors();

        expect($activity->refresh()->status)->toBe(ActivityStatus::Published);
    });
});

describe('UC-022: Given a live quiz, When the teacher adds a free-text question, Then it is refused', function () {
    it('refuses a free-text question in a live quiz and stores nothing', function () {
        $activity = qwNewQuiz();
        Quiz::factory()->for($activity, 'activity')->live()->create();
        $before = qwSnapshot();

        qwSave($this->teacher, $activity, 'content', ['questions' => [qwChoice((string) Str::uuid()), qwFreeText((string) Str::uuid())]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['questions.1.type'])
            ->assertJsonPath('errors', fn (array $errors) => ! array_key_exists('questions.0.type', $errors));

        expect(qwSnapshot())->toBe($before);
    });

    it('accepts the other question types in a live quiz', function () {
        $activity = qwNewQuiz();
        Quiz::factory()->for($activity, 'activity')->live()->create();

        qwSave($this->teacher, $activity, 'content', ['questions' => [qwChoice((string) Str::uuid()), qwMatching((string) Str::uuid())]])->assertOk();

        expect(qwPrompts($activity))->toHaveCount(2);
    });
});

describe('UC-022: Given a draft activity, Then no student can see it', function () {
    it('keeps a draft quiz with settings and questions hidden from every student', function () {
        $activity = qwNewQuiz();
        qwSave($this->teacher, $activity, 'settings', qwSettings())->assertOk();
        qwSave($this->teacher, $activity, 'content', ['questions' => [qwChoice((string) Str::uuid())]])->assertOk();
        $outsider = User::factory()->student()->create();

        foreach ([$this->student, $outsider] as $student) {
            $this->actingAs($student)->get(qwUrl("activities/{$activity->id}"))->assertNotFound();
            $this->actingAs($student)->get(qwUrl("activities/{$activity->id}/edit"))->assertNotFound();
            $this->actingAs($student)
                ->get(qwUrl('activities'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('activities', fn ($activities) => ! collect($activities)->contains('id', $activity->id)));

            expect(Gate::forUser($student)->allows('view', $activity))->toBeFalse();
        }
    });
});

describe('UC-022: Given a teacher who leaves the wizard at step 3, When they return, Then the steps they completed are restored', function () {
    it('restores the basics, settings and questions from the server', function () {
        $activity = qwNewQuiz();
        [$a, $b] = [(string) Str::uuid(), (string) Str::uuid()];
        qwSave($this->teacher, $activity, 'settings', qwSettings())->assertOk();
        qwSave($this->teacher, $activity, 'content', ['questions' => [qwMatching($b), qwChoice($a)]])->assertOk();

        $this->actingAs($this->teacher)
            ->get(qwUrl("activities/{$activity->id}/edit"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Activities/Wizard')
                ->where('basics.title', 'Sorting quiz')
                ->where('data.settings', [
                    'mode' => 'self_paced',
                    'time_limit_minutes' => 30,
                    'max_attempts' => 2,
                    'negative_marking' => true,
                    'negative_mark_fraction' => '25.00',
                    'pass_mark_percentage' => '50.50',
                    'score_visibility' => 'immediate',
                    'answers_visibility' => 'after_deadline',
                ])
                ->where('data.content.questions.0.id', $b)
                ->where('data.content.questions.0.type', 'matching')
                ->where('data.content.questions.0.pairs.1.right_text', 'n^2')
                ->where('data.content.questions.1.id', $a)
                ->where('data.content.questions.1.points', '2.00')
                ->where('data.content.questions.1.options.0.is_correct', true)
                ->where('steps', fn ($steps) => collect($steps)->where('key', '!=', 'review')->every(fn ($step) => $step['complete']))
                ->where('step', 'review'));
    });

    it('restores the settings alone and opens at the questions', function () {
        $activity = qwNewQuiz();
        qwSave($this->teacher, $activity, 'settings', qwSettings())->assertOk();

        $this->actingAs($this->teacher)
            ->get(qwUrl("activities/{$activity->id}/edit"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.settings.max_attempts', 2)
                ->where('data.content', null)
                ->where('step', 'content'));
    });
});

describe('UC-022: Given a published quiz with 1 submission, When the teacher changes a question\'s correct answer, Then it is refused', function () {
    beforeEach(function () {
        $this->activity = qwNewQuiz();
        $this->q = (string) Str::uuid();
        $this->optionIds = [(string) Str::uuid(), (string) Str::uuid()];
        qwSave($this->teacher, $this->activity, 'settings', qwSettings())->assertOk();
        qwSave($this->teacher, $this->activity, 'content', ['questions' => [qwChoice($this->q, 'Which sort is stable?', 0, $this->optionIds)]])->assertOk();
        $this->actingAs($this->teacher)->post(qwUrl("activities/{$this->activity->id}/publish"))->assertSessionHasNoErrors();

        QuizAttempt::factory()
            ->for(Quiz::query()->findOrFail($this->activity->id), 'quiz')
            ->for($this->student, 'student')
            ->submitted()
            ->create();
    });

    it('refuses a change of the correct answer with 409 and keeps the question', function () {
        $before = qwSnapshot();

        qwSave($this->teacher, $this->activity, 'content', ['questions' => [qwChoice($this->q, 'Which sort is stable?', 1, $this->optionIds)]])
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json');

        expect(qwSnapshot())->toBe($before)
            ->and(Question::query()->findOrFail($this->q)->options->firstWhere('is_correct', true)?->id)->toBe($this->optionIds[0]);
    });

    it('refuses other grading changes: points, added questions, negative marking', function () {
        $before = qwSnapshot();
        $points = qwChoice($this->q, 'Which sort is stable?', 0, $this->optionIds);
        $points['points'] = '4';

        qwSave($this->teacher, $this->activity, 'content', ['questions' => [$points]])->assertStatus(409);
        qwSave($this->teacher, $this->activity, 'content', ['questions' => [
            qwChoice($this->q, 'Which sort is stable?', 0, $this->optionIds), qwFreeText((string) Str::uuid()),
        ]])->assertStatus(409);
        qwSave($this->teacher, $this->activity, 'settings', qwSettings(['negative_marking' => false]))->assertStatus(409);

        expect(qwSnapshot())->toBe($before);
    });

    it('still allows changes that do not affect grading', function () {
        qwSave($this->teacher, $this->activity, 'content', ['questions' => [qwChoice($this->q, 'Which sorting algorithm is stable?', 0, $this->optionIds)]])->assertOk();
        qwSave($this->teacher, $this->activity, 'settings', qwSettings(['max_attempts' => 3]))->assertOk();

        expect(qwPrompts($this->activity))->toBe(['Which sorting algorithm is stable?'])
            ->and(Quiz::query()->findOrFail($this->activity->id)->max_attempts)->toBe(3);
    });

    it('marks the wizard as grading locked', function () {
        $this->actingAs($this->teacher)
            ->get(qwUrl("activities/{$this->activity->id}/edit"))
            ->assertInertia(fn (Assert $page) => $page->where('gradingLocked', true));
    });
});

describe('UC-022: Given a published quiz with 1 in-progress attempt and no submission, When the teacher removes a question or option, Then it is refused', function () {
    beforeEach(function () {
        $this->activity = qwNewQuiz();
        $this->q1 = (string) Str::uuid();
        $this->q2 = (string) Str::uuid();
        $this->optionIds = [(string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid()];
        $this->question = qwChoice($this->q1, 'Which sort is stable?', 0, $this->optionIds);
        $this->question['options'][] = ['id' => $this->optionIds[2], 'text' => 'Heapsort', 'is_correct' => false];
        qwSave($this->teacher, $this->activity, 'settings', qwSettings())->assertOk();
        qwSave($this->teacher, $this->activity, 'content', ['questions' => [$this->question, qwFreeText($this->q2)]])->assertOk();
        $this->actingAs($this->teacher)->post(qwUrl("activities/{$this->activity->id}/publish"))->assertSessionHasNoErrors();

        QuizAttempt::factory()
            ->for(Quiz::query()->findOrFail($this->activity->id), 'quiz')
            ->for($this->student, 'student')
            ->create();
    });

    it('refuses to remove a question', function () {
        $before = qwSnapshot();

        qwSave($this->teacher, $this->activity, 'content', ['questions' => [$this->question]])
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json');

        expect(qwSnapshot())->toBe($before)
            ->and(qwPrompts($this->activity))->toBe(['Which sort is stable?', 'Explain merge sort.']);
    });

    it('refuses to remove an option', function () {
        $before = qwSnapshot();
        $withoutOption = $this->question;
        array_pop($withoutOption['options']);

        qwSave($this->teacher, $this->activity, 'content', ['questions' => [$withoutOption, qwFreeText($this->q2)]])
            ->assertStatus(409);

        expect(qwSnapshot())->toBe($before)
            ->and(ChoiceOption::query()->whereKey($this->optionIds[2])->exists())->toBeTrue();
    });

    it('marks the wizard as grading locked', function () {
        $this->actingAs($this->teacher)
            ->get(qwUrl("activities/{$this->activity->id}/edit"))
            ->assertInertia(fn (Assert $page) => $page->where('gradingLocked', true));
    });
});

describe('DATA-008: Given a bank question copied into a quiz, When the teacher edits or deletes the bank question, Then the quiz copy is unchanged', function () {
    beforeEach(function () {
        $this->bankQuestion = Question::factory()->for($this->teacher, 'owner')
            ->withOptions([['Athens', true], ['Sparta', false]])
            ->create(['prompt' => 'Capital of Greece?', 'points' => '2.00']);

        $this->activity = qwNewQuiz();
        qwSave($this->teacher, $this->activity, 'settings', qwSettings())->assertOk();

        // The wizard copies the bank question with new ids for the question,
        // its options and its pairs.
        $bank = $this->actingAs($this->teacher)->getJson(qwUrl('question-bank/copyable'))->assertOk()->json('questions.0');
        $this->copyId = (string) Str::uuid();
        qwSave($this->teacher, $this->activity, 'content', ['questions' => [[
            ...$bank,
            'id' => $this->copyId,
            'options' => array_map(fn (array $option) => [...$option, 'id' => (string) Str::uuid()], $bank['options']),
        ]]])->assertOk();

        $this->copy = fn (): array => [
            'question' => Question::query()->findOrFail($this->copyId)->only(['type', 'prompt', 'points', 'in_bank', 'owner_id']),
            'options' => Question::query()->findOrFail($this->copyId)->options->map->only(['id', 'text', 'is_correct', 'position'])->all(),
        ];
    });

    it('stores a separate copy with its options', function () {
        $copy = ($this->copy)();

        expect($this->copyId)->not->toBe($this->bankQuestion->id)
            ->and($copy['question'])->toBe(['type' => QuestionType::SingleChoice, 'prompt' => 'Capital of Greece?', 'points' => '2.00', 'in_bank' => false, 'owner_id' => $this->teacher->id])
            ->and(array_column($copy['options'], 'text'))->toBe(['Athens', 'Sparta'])
            ->and(array_column($copy['options'], 'id'))->not->toContain($this->bankQuestion->options->first()?->id);
    });

    it('keeps the copy when the bank question is edited', function () {
        $before = ($this->copy)();

        $this->actingAs($this->teacher)->put(qwUrl("question-bank/{$this->bankQuestion->id}"), [
            'type' => 'single_choice',
            'prompt' => 'Capital of Sparta?',
            'points' => '5',
            'options' => [
                ['id' => $this->bankQuestion->options[0]->id, 'text' => 'Athens', 'is_correct' => false],
                ['id' => $this->bankQuestion->options[1]->id, 'text' => 'Sparta', 'is_correct' => true],
            ],
        ])->assertSessionHasNoErrors();

        expect($this->bankQuestion->refresh()->prompt)->toBe('Capital of Sparta?')
            ->and(($this->copy)())->toBe($before);
    });

    it('keeps the copy when the bank question is deleted', function () {
        $before = ($this->copy)();

        $this->actingAs($this->teacher)->delete(qwUrl("question-bank/{$this->bankQuestion->id}"))->assertSessionHasNoErrors();

        expect(Question::query()->find($this->bankQuestion->id))->toBeNull()
            ->and(($this->copy)())->toBe($before)
            ->and(qwPrompts($this->activity))->toBe(['Capital of Greece?']);
    });

    it('does not show quiz copies in the bank', function () {
        $this->actingAs($this->teacher)->getJson(qwUrl('question-bank/copyable'))
            ->assertOk()
            ->assertJsonCount(1, 'questions')
            ->assertJsonPath('questions.0.id', $this->bankQuestion->id);
    });
});

describe('OWN-016: Given a ROLE-Teacher user, When they read or modify an ENT-Question record the rule does not grant them, Then access is denied and nothing changes', function () {
    beforeEach(function () {
        $this->foreign = Question::factory()->for($this->otherTeacher, 'owner')
            ->withOptions([['Athens', true], ['Sparta', false]])
            ->create(['prompt' => 'Not yours']);
        Question::factory()->for($this->teacher, 'owner')->create(['prompt' => 'Mine']);

        $this->activity = qwNewQuiz();
        qwSave($this->teacher, $this->activity, 'settings', qwSettings())->assertOk();
    });

    it('lists only the teacher\'s own bank questions for copying', function () {
        $this->actingAs($this->teacher)->getJson(qwUrl('question-bank/copyable'))
            ->assertOk()
            ->assertJsonCount(1, 'questions')
            ->assertJsonPath('questions.0.prompt', 'Mine');
    });

    it('refuses to take over another teacher\'s question through the quiz', function () {
        $before = qwSnapshot();

        qwSave($this->teacher, $this->activity, 'content', ['questions' => [qwChoice($this->foreign->id, 'Hijacked')]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['questions.0.id']);

        expect(qwSnapshot())->toBe($before);
    });

    it('refuses to take over another question\'s options', function () {
        $before = qwSnapshot();

        qwSave($this->teacher, $this->activity, 'content', ['questions' => [
            qwChoice((string) Str::uuid(), 'Mine', 0, $this->foreign->options->pluck('id')->all()),
        ]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['questions.0.options.0.id', 'questions.0.options.1.id']);

        expect(qwSnapshot())->toBe($before);
    });

    it('refuses to change another teacher\'s quiz copies', function () {
        $otherSpace = CourseSpace::factory()->for($this->otherTeacher, 'teacher')->create();
        $otherQuiz = Quiz::factory()
            ->for(Activity::factory()->for($otherSpace, 'courseSpace')->create(), 'activity')
            ->withQuestions(1)
            ->create();
        $otherCopy = $otherQuiz->quizQuestions()->sole()->question;
        $before = qwSnapshot();

        // Through their own quiz.
        qwSave($this->teacher, $this->activity, 'content', ['questions' => [qwChoice($otherCopy->id, 'Hijacked')]])
            ->assertStatus(422);
        // Through the other teacher's quiz and the bank.
        qwSave($this->teacher, $otherQuiz->activity, 'content', ['questions' => []])->assertNotFound();
        $this->actingAs($this->teacher)->get(qwUrl("question-bank/{$otherCopy->id}/edit"))->assertForbidden();

        expect(qwSnapshot())->toBe($before)
            ->and(Gate::forUser($this->teacher)->allows('view', $otherCopy))->toBeFalse()
            ->and(Gate::forUser($this->otherTeacher)->allows('view', $otherCopy))->toBeFalse();
    });
});

describe('OWN-016: Given a ROLE-OrgAdmin or ROLE-Student user, When they read or modify an ENT-Question record the rule does not grant them, Then access is denied and nothing changes', function () {
    it('refuses them the bank for copying and every quiz question', function (string $who) {
        $actor = $this->{$who};
        Question::factory()->for($this->teacher, 'owner')->create();
        $activity = qwNewQuiz();
        qwSave($this->teacher, $activity, 'settings', qwSettings())->assertOk();
        $q = (string) Str::uuid();
        qwSave($this->teacher, $activity, 'content', ['questions' => [qwChoice($q)]])->assertOk();
        $before = qwSnapshot();

        $this->actingAs($actor)->getJson(qwUrl('question-bank/copyable'))
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/problem+json');
        qwSave($actor, $activity, 'content', ['questions' => [qwChoice($q, 'Hijacked', 1)]])->assertNotFound();
        $this->actingAs($actor)->get(qwUrl("question-bank/{$q}/edit"))->assertForbidden();
        $this->actingAs($actor)->delete(qwUrl("question-bank/{$q}"))->assertForbidden();

        expect(qwSnapshot())->toBe($before)
            ->and(Gate::forUser($actor)->allows('view', Question::query()->findOrFail($q)))->toBeFalse();
    })->with(['admin', 'student']);
});

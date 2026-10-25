<?php

use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use App\Modules\Live\Support\LiveQuizType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| P5-T4, ARCH-009: the quiz's activity page actions, contributed by
| LiveQuizType through Activities' optional ProvidesActivityActions
| contract. The owning teacher of a published live quiz gets 'Open live
| quiz' to the monitor; nobody gets the self-paced 'Open quiz' on a live
| quiz; an enrolled student gets 'Open quiz' (open_quiz) on a self-paced
| quiz.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create();
    $this->outsider = User::factory()->student()->create();

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();
});

function aaQuiz(bool $live, string $status = 'published'): Activity
{
    $activity = Activity::factory()->for(test()->space, 'courseSpace');
    $activity = match ($status) {
        'published' => $activity->published(),
        'closed' => $activity->closed(),
        default => $activity,
    };
    $quiz = Quiz::factory()->for($activity->create(), 'activity');

    return ($live ? $quiz->live() : $quiz)->withQuestions(1)->create()->activity;
}

function aaPage(Activity $activity, User $as): TestResponse
{
    return test()->actingAs($as)->get(test()->tenantUrl(test()->acme, "activities/{$activity->id}"));
}

/**
 * @return list<string>
 */
function aaKeys(Activity $activity, User $user): array
{
    $type = app(ActivityTypes::class)->for($activity);
    expect($type)->toBeInstanceOf(LiveQuizType::class);

    return array_map(fn ($action): string => $action->key, $type->actions($activity, $user));
}

describe('a published live quiz', function () {
    it('shows its owning teacher \'Open live quiz\' linking to the monitor and no self-paced \'Open quiz\'', function () {
        $activity = aaQuiz(live: true);

        aaPage($activity, $this->teacher)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Activities/Show')
            ->has('actions', 1)
            ->where('actions.0.key', 'open_live_quiz')
            ->where('actions.0.href', "/live/quizzes/{$activity->id}")
            ->where('actions.0.label', 'live.actions.open_live_quiz'));

        $this->actingAs($this->teacher)
            ->get($this->tenantUrl($this->acme, "live/quizzes/{$activity->id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Live/Monitor'));
    });

    it('shows another teacher of the organisation no \'Open live quiz\'', function () {
        $activity = aaQuiz(live: true);

        aaPage($activity, $this->otherTeacher)->assertForbidden();
        expect(aaKeys($activity, $this->otherTeacher))->toBe([]);

        // Not even when that teacher owns another space of the organisation.
        CourseSpace::factory()->for($this->otherTeacher, 'teacher')->create();
        expect(aaKeys($activity, $this->otherTeacher))->toBe([]);
    });

    it('shows an enrolled student no self-paced \'Open quiz\'', function () {
        $activity = aaQuiz(live: true);

        aaPage($activity, $this->student)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('actions', fn ($actions) => collect($actions)->pluck('key')->doesntContain('open_quiz')
                && collect($actions)->pluck('key')->doesntContain('open_live_quiz')));
    });

    it('offers \'Open live quiz\' only while the live quiz is published', function () {
        expect(aaKeys(aaQuiz(live: true, status: 'draft'), $this->teacher))->toBe([])
            ->and(aaKeys(aaQuiz(live: true, status: 'closed'), $this->teacher))->toBe([]);
    });
});

describe('a published self-paced quiz', function () {
    it('shows an enrolled student the self-paced \'Open quiz\' (activity action open_quiz)', function () {
        $activity = aaQuiz(live: false);

        aaPage($activity, $this->student)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('actions', 1)
            ->where('actions.0.key', 'open_quiz')
            ->where('actions.0.href', route('assessment.quizzes.show', $activity, false))
            ->where('actions.0.label', 'live.actions.open_quiz'));
    });

    it('shows its owning teacher no \'Open live quiz\' (nor the student\'s \'Open quiz\')', function () {
        $activity = aaQuiz(live: false);

        aaPage($activity, $this->teacher)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('actions', []));
    });

    it('shows a student who is not enrolled nothing, and a draft to nobody', function () {
        $activity = aaQuiz(live: false);

        aaPage($activity, $this->outsider)->assertForbidden();
        expect(aaKeys($activity, $this->outsider))->toBe([])
            ->and(aaKeys(aaQuiz(live: false, status: 'draft'), $this->student))->toBe([]);
    });
});

it('gives an activity whose type contributes no actions none (a poll)', function () {
    $poll = Activity::factory()->for($this->space, 'courseSpace')->published()->create(['type' => 'poll']);

    aaPage($poll, $this->teacher)->assertOk()->assertInertia(fn (Assert $page) => $page->where('actions', []));
});

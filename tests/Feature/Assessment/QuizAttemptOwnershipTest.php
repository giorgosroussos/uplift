<?php

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\GradingStatus;
use App\Modules\Assessment\Exceptions\InvalidStateTransition;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

/*
| OWN-006 (ENT-QuizAttempt) and OWN-007 (ENT-Answer), SEC-007: students
| reach only their own attempts and answers, teachers only those of quizzes
| in course spaces they own, org admins no individual attempt or answer.
| Plus the state models of ENT-QuizAttempt and ENT-Answer, and tenant
| isolation of both.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create();
    $this->classmate = User::factory()->student()->create();
    $this->admin = User::factory()->orgAdmin()->create();

    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
    Enrolment::factory()->for($space, 'courseSpace')->for($this->student, 'student')->create();
    Enrolment::factory()->for($space, 'courseSpace')->for($this->classmate, 'student')->create();

    $this->quiz = Quiz::factory()
        ->for(Activity::factory()->for($space, 'courseSpace')->published()->create(), 'activity')
        ->withQuestions(2)
        ->create();

    $this->attempt = QuizAttempt::factory()->for($this->quiz, 'quiz')->for($this->student, 'student')->create();
    $this->answer = Answer::factory()->for($this->attempt, 'attempt')->create(['text_response' => 'My answer']);
    $this->otherAttempt = QuizAttempt::factory()->for($this->quiz, 'quiz')->for($this->classmate, 'student')->submitted()->create();
    $this->otherAnswer = Answer::factory()->for($this->otherAttempt, 'attempt')->create();
});

/**
 * @return array<string, mixed>
 */
function attemptSnapshot(): array
{
    return [
        'attempts' => QuizAttempt::query()->withoutGlobalScopes()->orderBy('id')->get()->makeVisible('earned_points')->toArray(),
        'answers' => Answer::query()->withoutGlobalScopes()->orderBy('id')->get()->makeVisible(['text_response', 'feedback'])->toArray(),
    ];
}

describe('OWN-006: Given a ROLE-Student user, When they read or modify an ENT-QuizAttempt record the rule does not grant them, Then access is denied and nothing changes', function () {
    it('lets a student read and work on only their own attempts', function () {
        $before = attemptSnapshot();
        $gate = Gate::forUser($this->student);

        expect($gate->allows('view', $this->attempt))->toBeTrue()
            ->and($gate->allows('update', $this->attempt))->toBeTrue()
            ->and($gate->allows('view', $this->otherAttempt))->toBeFalse()
            ->and($gate->allows('update', $this->otherAttempt))->toBeFalse()
            ->and(attemptSnapshot())->toBe($before);
    });
});

describe('OWN-006: Given a ROLE-Teacher user, When they read or modify an ENT-QuizAttempt record the rule does not grant them, Then access is denied and nothing changes', function () {
    it('lets a teacher read only attempts of quizzes in course spaces they own', function () {
        $before = attemptSnapshot();

        expect(Gate::forUser($this->teacher)->allows('view', $this->attempt))->toBeTrue()
            ->and(Gate::forUser($this->otherTeacher)->allows('view', $this->attempt))->toBeFalse()
            ->and(Gate::forUser($this->otherTeacher)->allows('update', $this->attempt))->toBeFalse()
            // Teachers never answer for a student.
            ->and(Gate::forUser($this->teacher)->allows('update', $this->attempt))->toBeFalse()
            ->and(attemptSnapshot())->toBe($before);
    });
});

describe('OWN-006: Given a ROLE-OrgAdmin user, When they read or modify an ENT-QuizAttempt record the rule does not grant them, Then access is denied and nothing changes', function () {
    it('never lets an org admin read or change an individual attempt', function () {
        $before = attemptSnapshot();

        expect(Gate::forUser($this->admin)->allows('view', $this->attempt))->toBeFalse()
            ->and(Gate::forUser($this->admin)->allows('update', $this->attempt))->toBeFalse()
            ->and(Gate::forUser($this->admin)->allows('view', $this->otherAttempt))->toBeFalse()
            ->and(attemptSnapshot())->toBe($before);
    });
});

describe('OWN-007: Given a ROLE-Student user, When they read or modify an ENT-Answer record the rule does not grant them, Then access is denied and nothing changes', function () {
    it('lets a student read and give only answers of their own attempts', function () {
        $before = attemptSnapshot();
        $gate = Gate::forUser($this->student);

        expect($gate->allows('view', $this->answer))->toBeTrue()
            ->and($gate->allows('update', $this->answer))->toBeTrue()
            ->and($gate->allows('grade', $this->answer))->toBeFalse()
            ->and($gate->allows('view', $this->otherAnswer))->toBeFalse()
            ->and($gate->allows('update', $this->otherAnswer))->toBeFalse()
            ->and(attemptSnapshot())->toBe($before);
    });
});

describe('OWN-007: Given a ROLE-Teacher user, When they read or modify an ENT-Answer record the rule does not grant them, Then access is denied and nothing changes', function () {
    it('lets a teacher grade only answers of quizzes in course spaces they own', function () {
        $before = attemptSnapshot();

        expect(Gate::forUser($this->teacher)->allows('view', $this->answer))->toBeTrue()
            ->and(Gate::forUser($this->teacher)->allows('grade', $this->answer))->toBeTrue()
            ->and(Gate::forUser($this->teacher)->allows('update', $this->answer))->toBeFalse()
            ->and(Gate::forUser($this->otherTeacher)->allows('view', $this->answer))->toBeFalse()
            ->and(Gate::forUser($this->otherTeacher)->allows('grade', $this->answer))->toBeFalse()
            ->and(attemptSnapshot())->toBe($before);
    });
});

describe('OWN-007: Given a ROLE-OrgAdmin user, When they read or modify an ENT-Answer record the rule does not grant them, Then access is denied and nothing changes', function () {
    it('never lets an org admin read, change or grade an individual answer', function () {
        $before = attemptSnapshot();

        foreach (['view', 'update', 'grade'] as $ability) {
            expect(Gate::forUser($this->admin)->allows($ability, $this->answer))->toBeFalse();
        }

        expect(attemptSnapshot())->toBe($before);
    });
});

describe('tenant isolation', function () {
    it('hides attempts and answers of other organisations and refuses their actors', function () {
        $globex = $this->createOrganization(['subdomain' => 'globex']);
        $this->actingAsTenant($globex);
        $foreignTeacher = User::factory()->teacher()->create();

        expect(QuizAttempt::query()->count())->toBe(0)
            ->and(Answer::query()->count())->toBe(0)
            ->and(Gate::forUser($foreignTeacher)->allows('view', $this->attempt))->toBeFalse()
            ->and(Gate::forUser($foreignTeacher)->allows('grade', $this->answer))->toBeFalse();
    });
});

describe('ENT-QuizAttempt: Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged', function () {
    it('allows in_progress -> submitted -> graded', function () {
        $this->attempt->transitionTo(AttemptStatus::Submitted);
        $this->attempt->transitionTo(AttemptStatus::Graded);

        expect($this->attempt->refresh()->status)->toBe(AttemptStatus::Graded);
    });

    it('rejects every other transition', function (AttemptStatus $from, AttemptStatus $to) {
        $attempt = QuizAttempt::factory()->for($this->quiz, 'quiz')->create();

        foreach ([AttemptStatus::Submitted, AttemptStatus::Graded] as $step) {
            if ($attempt->status === $from) {
                break;
            }
            $attempt->transitionTo($step);
        }

        expect(fn () => $attempt->transitionTo($to))->toThrow(InvalidStateTransition::class)
            ->and($attempt->status)->toBe($from)
            ->and($attempt->refresh()->status)->toBe($from);

        // Also when the status is written directly (writing the same status is no change).
        if ($from !== $to) {
            $attempt->status = $to;
            expect(fn () => $attempt->save())->toThrow(InvalidStateTransition::class)
                ->and($attempt->refresh()->status)->toBe($from);
        }
    })->with([
        'in_progress -> graded' => [AttemptStatus::InProgress, AttemptStatus::Graded],
        'in_progress -> in_progress' => [AttemptStatus::InProgress, AttemptStatus::InProgress],
        'submitted -> in_progress' => [AttemptStatus::Submitted, AttemptStatus::InProgress],
        'submitted -> submitted' => [AttemptStatus::Submitted, AttemptStatus::Submitted],
        'graded -> in_progress' => [AttemptStatus::Graded, AttemptStatus::InProgress],
        'graded -> submitted' => [AttemptStatus::Graded, AttemptStatus::Submitted],
    ]);

    it('creates attempts only in the initial state', function () {
        $count = QuizAttempt::query()->count();

        expect(fn () => QuizAttempt::factory()->for($this->quiz, 'quiz')->create(['status' => AttemptStatus::Submitted]))
            ->toThrow(InvalidStateTransition::class)
            ->and(QuizAttempt::query()->count())->toBe($count);
    });
});

describe('ENT-Answer: Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged', function () {
    it('allows pending -> graded', function () {
        $this->answer->transitionTo(GradingStatus::Graded);

        expect($this->answer->refresh()->grading_status)->toBe(GradingStatus::Graded);
    });

    it('rejects every other transition', function (GradingStatus $from, GradingStatus $to) {
        if ($from === GradingStatus::Graded) {
            $this->answer->transitionTo(GradingStatus::Graded);
        }

        expect(fn () => $this->answer->transitionTo($to))->toThrow(InvalidStateTransition::class)
            ->and($this->answer->grading_status)->toBe($from)
            ->and($this->answer->refresh()->grading_status)->toBe($from);

        if ($from !== $to) {
            $this->answer->grading_status = $to;
            expect(fn () => $this->answer->save())->toThrow(InvalidStateTransition::class)
                ->and($this->answer->refresh()->grading_status)->toBe($from);
        }
    })->with([
        'pending -> auto_graded' => [GradingStatus::Pending, GradingStatus::AutoGraded],
        'pending -> pending' => [GradingStatus::Pending, GradingStatus::Pending],
        'graded -> pending' => [GradingStatus::Graded, GradingStatus::Pending],
        'graded -> auto_graded' => [GradingStatus::Graded, GradingStatus::AutoGraded],
        'graded -> graded' => [GradingStatus::Graded, GradingStatus::Graded],
    ]);

    it('creates answers only in the initial state', function () {
        $count = Answer::query()->count();

        expect(fn () => Answer::factory()->for($this->otherAttempt, 'attempt')->create(['grading_status' => GradingStatus::Graded]))
            ->toThrow(InvalidStateTransition::class)
            ->and(Answer::query()->count())->toBe($count);
    });

    it('keeps answers and feedback out of transition errors', function () {
        $this->answer->transitionTo(GradingStatus::Graded);

        try {
            $this->answer->transitionTo(GradingStatus::Pending);
        } catch (InvalidStateTransition $e) {
            expect($e->getMessage())->not->toContain('My answer');
        }
    });
});

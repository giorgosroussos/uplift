<?php

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\GradingStatus;
use App\Modules\Assessment\Enums\ResultVisibility;
use App\Modules\Assessment\Exceptions\InvalidStateTransition;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| SCOPE-023: answers are created pending and move on submission to
| auto_graded (choice, true/false, matching) or stay pending (free text);
| after_deadline visibility on a quiz without a deadline means visible
| immediately after submission.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->actingAsTenant($this->createOrganization(['subdomain' => 'acme']));
    $this->teacher = User::factory()->teacher()->create();
    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
});

function quizWith(CourseSpace $space, array $quiz = [], array $activity = [], ?string $questionState = null): Quiz
{
    $questions = $questionState === null ? null : Question::factory()->{$questionState}();

    return Quiz::factory()
        ->for(Activity::factory()->for($space, 'courseSpace')->published()->create($activity), 'activity')
        ->withQuestions(1, $questions)
        ->create($quiz);
}

describe('SCOPE-023: answer grading status on submission', function () {
    it('creates answers as pending', function () {
        $quiz = quizWith($this->space);
        $answer = Answer::factory()->for(QuizAttempt::factory()->for($quiz, 'quiz')->create(), 'attempt')->create();

        expect($answer->refresh()->grading_status)->toBe(GradingStatus::Pending);
    });

    it('moves auto-gradable answers to auto_graded and leaves free text pending', function (?string $state, GradingStatus $expected) {
        $quiz = quizWith($this->space, questionState: $state);
        $answer = Answer::factory()->for(QuizAttempt::factory()->for($quiz, 'quiz')->create(), 'attempt')->create();

        $answer->applySubmission();

        expect($answer->refresh()->grading_status)->toBe($expected);
    })->with([
        'single choice' => [null, GradingStatus::AutoGraded],
        'multiple choice' => ['multipleChoice', GradingStatus::AutoGraded],
        'true/false' => ['trueFalse', GradingStatus::AutoGraded],
        'matching' => ['matching', GradingStatus::AutoGraded],
        'free text' => ['freeText', GradingStatus::Pending],
    ]);

    it('refuses to apply a submission to an answer that is no longer pending', function () {
        $quiz = quizWith($this->space);
        $answer = Answer::factory()->for(QuizAttempt::factory()->for($quiz, 'quiz')->create(), 'attempt')->graded()->create();

        expect(fn () => $answer->applySubmission())->toThrow(InvalidStateTransition::class)
            ->and($answer->refresh()->grading_status)->toBe(GradingStatus::Graded);
    });
});

describe('SCOPE-023: result visibility', function () {
    it('shows after_deadline results immediately after submission when the quiz has no deadline', function () {
        $quiz = quizWith($this->space, [
            'score_visibility' => ResultVisibility::AfterDeadline,
            'answers_visibility' => ResultVisibility::AfterDeadline,
        ], ['deadline_at' => null]);

        $inProgress = QuizAttempt::factory()->for($quiz, 'quiz')->create();
        $submitted = QuizAttempt::factory()->for($quiz, 'quiz')->submitted()->create();

        expect($quiz->isScoreVisible($inProgress))->toBeFalse()
            ->and($quiz->areAnswersVisible($inProgress))->toBeFalse()
            ->and($quiz->isScoreVisible($submitted))->toBeTrue()
            ->and($quiz->areAnswersVisible($submitted))->toBeTrue();
    });

    it('waits for the deadline when the quiz has one', function () {
        $deadline = CarbonImmutable::parse('2030-01-10 12:00:00', 'UTC');
        $quiz = quizWith($this->space, [
            'score_visibility' => ResultVisibility::AfterDeadline,
            'answers_visibility' => ResultVisibility::AfterDeadline,
        ], ['deadline_at' => $deadline]);
        $submitted = QuizAttempt::factory()->for($quiz, 'quiz')->submitted()->create();

        expect($quiz->isScoreVisible($submitted, $deadline->subMinute()))->toBeFalse()
            ->and($quiz->areAnswersVisible($submitted, $deadline->subMinute()))->toBeFalse()
            ->and($quiz->isScoreVisible($submitted, $deadline))->toBeTrue()
            ->and($quiz->areAnswersVisible($submitted, $deadline->addMinute()))->toBeTrue();
    });

    it('shows immediate results only after submission and hidden results never', function () {
        $quiz = quizWith($this->space, [
            'score_visibility' => ResultVisibility::Immediate,
            'answers_visibility' => ResultVisibility::Hidden,
        ], ['deadline_at' => null]);
        $inProgress = QuizAttempt::factory()->for($quiz, 'quiz')->create();
        $submitted = QuizAttempt::factory()->for($quiz, 'quiz')->submitted()->create();

        expect($quiz->isScoreVisible($inProgress))->toBeFalse()
            ->and($quiz->isScoreVisible($submitted))->toBeTrue()
            ->and($quiz->areAnswersVisible($submitted))->toBeFalse();
    });
});

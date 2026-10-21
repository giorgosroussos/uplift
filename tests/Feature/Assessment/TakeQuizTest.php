<?php

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\AssessmentServiceProvider;
use App\Modules\Assessment\Database\Factories\QuestionFactory;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\GradingStatus;
use App\Modules\Assessment\Enums\ResultVisibility;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\MatchingPair;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Assessment\Support\MatchingItems;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-012 (P3-T4): a student takes a self-paced quiz. Attempts start and end
| by the server's clock (DATA-004, SCOPE-024), answers are autosaved and
| scored on submission with partial credit and optional negative marking
| (SCOPE-024), attempts are auto-submitted when their time is up or the
| quiz is closed, and OWN-006 / OWN-007 hold on every endpoint.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create();
    $this->classmate = User::factory()->student()->create();
    $this->outsider = User::factory()->student()->create();
    $this->admin = User::factory()->orgAdmin()->create();

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->classmate, 'student')->create();
});

function tqUrl(string $path): string
{
    return test()->tenantUrl(test()->acme, ltrim($path, '/'));
}

/**
 * A published self-paced quiz of the course space with the given settings
 * and questions (question factories, each placed once, in order).
 *
 * @param  array<string, mixed>  $settings
 * @param  list<QuestionFactory>  $questions
 * @param  array<string, mixed>  $activity
 */
function tqQuiz(array $settings = [], array $questions = [], array $activity = [], string $state = 'published'): Quiz
{
    $factory = Activity::factory()->for(test()->space, 'courseSpace');
    $factory = match ($state) {
        'published' => $factory->published(),
        'closed' => $factory->closed(),
        default => $factory,
    };

    $quiz = Quiz::factory()->for($factory->create($activity), 'activity');

    foreach ($questions === [] ? [Question::factory()] : $questions as $question) {
        $quiz = $quiz->withQuestions(1, $question);
    }

    return $quiz->create($settings);
}

/**
 * @return list<QuizQuestion>
 */
function tqPlacements(Quiz $quiz): array
{
    return $quiz->quizQuestions()->with(['question.options', 'question.pairs'])->get()->all();
}

function tqStart(Quiz $quiz, ?User $student = null): TestResponse
{
    return test()->actingAs($student ?? test()->student)->post(tqUrl("quizzes/{$quiz->activity_id}/attempts"));
}

/**
 * Starts an attempt and returns it; for a further attempt the clock moves
 * on a second first so the student's attempts never share a start time.
 */
function tqAttempt(Quiz $quiz, ?User $student = null): QuizAttempt
{
    $attempts = QuizAttempt::query()
        ->where('quiz_id', $quiz->activity_id)
        ->where('student_id', ($student ?? test()->student)->id);

    if ((clone $attempts)->exists()) {
        test()->travel(1)->seconds();
    }

    tqStart($quiz, $student)->assertRedirect();

    return $attempts->where('status', AttemptStatus::InProgress->value)->sole();
}

/**
 * @param  array<string, mixed>  $data
 */
function tqAnswer(QuizAttempt $attempt, QuizQuestion $placement, array $data, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->student)
        ->putJson(tqUrl("quiz-attempts/{$attempt->id}/answers/{$placement->id}"), $data);
}

/**
 * Option ids of a choice question by option text.
 *
 * @param  list<string>  $texts
 * @return list<string>
 */
function tqOptions(QuizQuestion $placement, array $texts): array
{
    return $placement->question->options->whereIn('text', $texts)->pluck('id')->values()->all();
}

function tqSubmit(QuizAttempt $attempt, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->student)->post(tqUrl("quiz-attempts/{$attempt->id}/submit"));
}

function tqPoints(QuizAttempt $attempt, QuizQuestion $placement): ?string
{
    return Answer::query()->where('attempt_id', $attempt->id)->where('quiz_question_id', $placement->id)->value('points_awarded');
}

function tqEarned(QuizAttempt $attempt): ?string
{
    return QuizAttempt::query()->whereKey($attempt->id)->value('earned_points');
}

/**
 * @return array<string, mixed>
 */
function tqSnapshot(): array
{
    return [
        'attempts' => QuizAttempt::query()->withoutGlobalScopes()->orderBy('id')->get()->makeVisible('earned_points')->toArray(),
        'answers' => Answer::query()->withoutGlobalScopes()->orderBy('id')->get()->makeVisible(['text_response', 'feedback'])->toArray(),
    ];
}

function tqMultipleChoice(): QuestionFactory
{
    return Question::factory()->multipleChoice()->withOptions([['A', true], ['B', true], ['C', false], ['D', false]]);
}

describe('starting an attempt', function () {
    it('starts an attempt on the server clock and opens it', function () {
        $quiz = tqQuiz(['time_limit' => 600], [Question::factory(), Question::factory()->state(['points' => '2.50'])]);

        $response = tqStart($quiz);
        $attempt = QuizAttempt::query()->sole();

        $response->assertRedirect(tqUrl("quiz-attempts/{$attempt->id}"));
        expect($attempt->status)->toBe(AttemptStatus::InProgress)
            ->and($attempt->started_at->toDateTimeString())->toBe('2026-11-02 10:00:00')
            ->and($attempt->ends_at?->toDateTimeString())->toBe('2026-11-02 10:10:00')
            ->and($attempt->max_points)->toBe('3.50');
    });

    it('UC-012: Given a quiz allowing 2 attempts and a student with 2 submitted attempts, When they start another, Then it is refused', function () {
        $quiz = tqQuiz(['max_attempts' => 2]);

        foreach ([1, 2] as $_) {
            tqSubmit(tqAttempt($quiz))->assertRedirect();
        }

        tqStart($quiz)->assertSessionHasErrors(['attempt' => __('assessment.attempt.refused.no_attempts_left')]);

        expect(QuizAttempt::query()->count())->toBe(2);
    });

    it('UC-012: Given a quiz allowing 2 attempts with 1 submitted and 1 in progress, When the student starts another, Then it is refused', function () {
        $quiz = tqQuiz(['max_attempts' => 2]);
        tqSubmit(tqAttempt($quiz));
        $open = tqAttempt($quiz);

        tqStart($quiz)->assertSessionHasErrors(['attempt' => __('assessment.attempt.refused.in_progress')]);

        expect(QuizAttempt::query()->count())->toBe(2)
            ->and($open->refresh()->status)->toBe(AttemptStatus::InProgress);
    });

    it('refuses a second attempt while one is in progress even with attempts left, and offers to continue it', function () {
        $quiz = tqQuiz(['max_attempts' => 5]);
        $open = tqAttempt($quiz);

        tqStart($quiz)->assertSessionHasErrors('attempt');

        test()->actingAs(test()->student)->get(tqUrl("quizzes/{$quiz->activity_id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Assessment/Attempts/Quiz')
                ->where('inProgressId', $open->id)
                ->where('canStart', false)
                ->where('attemptsLeft', 4));
        expect(QuizAttempt::query()->count())->toBe(1);
    });

    it('refuses attempts on a closed quiz, after the deadline and on a live quiz', function (string $case) {
        $quiz = match ($case) {
            'closed' => tqQuiz(state: 'closed'),
            'deadline' => tqQuiz(activity: ['deadline_at' => now()->subMinute()]),
            'live' => tqQuiz(['mode' => 'live']),
        };

        tqStart($quiz)->assertSessionHasErrors('attempt');

        expect(QuizAttempt::query()->count())->toBe(0);
    })->with(['closed', 'deadline', 'live']);

    it('does not show a live quiz as a self-paced quiz (SCOPE-016)', function () {
        $quiz = tqQuiz(['mode' => 'live']);

        test()->actingAs(test()->student)->get(tqUrl("quizzes/{$quiz->activity_id}"))->assertNotFound();
    });

    it('UC-012: Given a 30-minute limit and a deadline 10 minutes after the start, Then the attempt ends at the deadline', function () {
        $quiz = tqQuiz(['time_limit' => 1800], activity: ['deadline_at' => now()->addMinutes(10)]);

        $attempt = tqAttempt($quiz);

        expect($attempt->ends_at?->toDateTimeString())->toBe('2026-11-02 10:10:00');
    });

    it('ends an attempt at started_at + time limit when the deadline is later, and never without either', function () {
        $limited = tqAttempt(tqQuiz(['time_limit' => 600], activity: ['deadline_at' => now()->addDay()]));
        $open = tqAttempt(tqQuiz());

        expect($limited->ends_at?->toDateTimeString())->toBe('2026-11-02 10:10:00')
            ->and($open->ends_at)->toBeNull();
    });
});

describe('answering and the server-side timer', function () {
    it('autosaves answers of every type and replaces them on change', function () {
        $quiz = tqQuiz([], [Question::factory(), tqMultipleChoice(), Question::factory()->matching(), Question::factory()->freeText()]);
        [$single, $multiple, $matching, $text] = tqPlacements($quiz);
        $attempt = tqAttempt($quiz);
        [$france, $greece] = $matching->question->pairs->all();

        tqAnswer($attempt, $single, ['option_ids' => tqOptions($single, ['B'])])->assertOk();
        tqAnswer($attempt, $single, ['option_ids' => tqOptions($single, ['A'])])->assertOk();
        tqAnswer($attempt, $multiple, ['option_ids' => tqOptions($multiple, ['A', 'C'])])->assertOk();
        tqAnswer($attempt, $matching, ['matches' => [
            ['left_id' => $france->id, 'right_id' => MatchingItems::rightId($attempt, $france->id)],
            ['left_id' => $greece->id, 'right_id' => MatchingItems::rightId($attempt, $france->id)],
        ]])->assertOk();
        tqAnswer($attempt, $text, ['text' => 'Divide and conquer'])->assertOk();

        test()->actingAs(test()->student)->get(tqUrl("quiz-attempts/{$attempt->id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Assessment/Attempts/Take')
                ->where("answers.{$single->id}.option_ids", tqOptions($single, ['A']))
                ->where("answers.{$multiple->id}.option_ids", fn ($ids) => collect($ids)->sort()->values()->all() === collect(tqOptions($multiple, ['A', 'C']))->sort()->values()->all())
                ->where("answers.{$matching->id}.matches.0.left_id", $france->id)
                ->where("answers.{$text->id}.text", 'Divide and conquer')
                ->where('score', null));

        expect(Answer::query()->count())->toBe(4)
            ->and(Answer::query()->pluck('grading_status')->unique()->all())->toBe([GradingStatus::Pending]);
    });

    it('never reveals which options or pairs are correct while taking the quiz', function () {
        $quiz = tqQuiz([], [Question::factory(), Question::factory()->matching()]);
        [, $matching] = tqPlacements($quiz);
        $attempt = tqAttempt($quiz);
        $pairIds = $matching->question->pairs->pluck('id')->all();

        test()->actingAs(test()->student)->get(tqUrl("quiz-attempts/{$attempt->id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('questions.0.options.0.is_correct')
                ->where('questions.1.right', fn ($right) => collect($right)->pluck('id')->intersect($pairIds)->isEmpty()));
    });

    it('refuses answers that do not fit the question', function () {
        $quiz = tqQuiz([], [Question::factory(), Question::factory()->matching()]);
        $other = tqQuiz();
        [$single, $matching] = tqPlacements($quiz);
        [$foreign] = tqPlacements($other);
        $attempt = tqAttempt($quiz);

        tqAnswer($attempt, $single, ['option_ids' => tqOptions($single, ['A', 'B'])])->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json');
        tqAnswer($attempt, $single, ['option_ids' => tqOptions($foreign, ['A'])])->assertUnprocessable();
        // Right ids are opaque per attempt: a raw pair id is refused.
        $pair = $matching->question->pairs->first();
        tqAnswer($attempt, $matching, ['matches' => [['left_id' => $pair->id, 'right_id' => $pair->id]]])->assertUnprocessable();
        // A question of another quiz does not exist for this attempt.
        tqAnswer($attempt, $foreign, ['option_ids' => []])->assertNotFound();

        expect(Answer::query()->count())->toBe(0);
    });

    it('UC-012: Given an attempt with a 10-minute limit and 3 of 5 questions answered, When 10 minutes pass, Then the attempt is submitted with those 3 answers', function () {
        $quiz = tqQuiz(['time_limit' => 600], array_fill(0, 5, Question::factory()));
        $placements = tqPlacements($quiz);
        $attempt = tqAttempt($quiz);

        foreach (array_slice($placements, 0, 3) as $placement) {
            tqAnswer($attempt, $placement, ['option_ids' => tqOptions($placement, ['A'])])->assertOk();
        }

        $this->travel(10)->minutes();
        $this->artisan('assessment:submit-due-attempts')->assertSuccessful();

        $attempt->refresh();
        expect($attempt->status)->toBe(AttemptStatus::Graded)
            ->and($attempt->submitted_at?->toDateTimeString())->toBe('2026-11-02 10:10:00')
            ->and($attempt->submitted_by)->toBeNull()
            ->and($attempt->answers()->count())->toBe(3)
            ->and(tqEarned($attempt))->toBe('3.00')
            ->and($attempt->max_points)->toBe('5.00');
    });

    it('submits a due attempt on the next request even before the scheduler runs', function () {
        $quiz = tqQuiz(['time_limit' => 600]);
        $attempt = tqAttempt($quiz);

        $this->travel(11)->minutes();

        test()->actingAs(test()->student)->get(tqUrl("quiz-attempts/{$attempt->id}"))
            ->assertInertia(fn (Assert $page) => $page->where('attempt.status', 'graded')->where('attempt.remaining_seconds', 0));
        expect($attempt->refresh()->submitted_at?->toDateTimeString())->toBe('2026-11-02 10:10:00');
    });

    it('UC-012: Given the timer is enforced on the server, When an answer arrives after the limit, Then it is not counted', function () {
        $quiz = tqQuiz(['time_limit' => 600], [Question::factory(), Question::factory()]);
        [$first, $second] = tqPlacements($quiz);
        $attempt = tqAttempt($quiz);
        tqAnswer($attempt, $first, ['option_ids' => tqOptions($first, ['A'])])->assertOk();

        $this->travel(601)->seconds();

        tqAnswer($attempt, $second, ['option_ids' => tqOptions($second, ['A'])])
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', AssessmentServiceProvider::PROBLEM_ATTEMPT_CLOSED);
        // Changing an earlier answer after the limit is not counted either.
        tqAnswer($attempt, $first, ['option_ids' => tqOptions($first, ['B'])])->assertStatus(409);

        $attempt->refresh();
        expect($attempt->status)->toBe(AttemptStatus::Graded)
            ->and($attempt->answers()->count())->toBe(1)
            ->and(tqPoints($attempt, $first))->toBe('1.00')
            ->and(tqPoints($attempt, $second))->toBeNull()
            ->and(tqEarned($attempt))->toBe('1.00');
    });

    it('ignores the client clock: only the server decides when an attempt ends', function () {
        $quiz = tqQuiz(['time_limit' => 600]);
        [$placement] = tqPlacements($quiz);
        $attempt = tqAttempt($quiz);

        $this->travel(9)->minutes();

        // A client-side timestamp in the request is ignored.
        tqAnswer($attempt, $placement, ['option_ids' => tqOptions($placement, ['A']), 'answered_at' => '2026-11-02T12:00:00Z'])->assertOk();
        expect($attempt->refresh()->status)->toBe(AttemptStatus::InProgress);

        $this->travel(2)->minutes();
        tqAnswer($attempt, $placement, ['option_ids' => tqOptions($placement, ['B']), 'answered_at' => '2026-11-02T10:05:00Z'])->assertStatus(409);
        expect(tqPoints($attempt, $placement))->toBe('1.00');
    });
});

describe('scoring (SCOPE-024)', function () {
    it('UC-012: Given negative marking enabled with -0.25 per wrong answer on a 1-point question, When the answer is wrong, Then that question scores -0.25; given negative marking disabled, Then it scores 0', function (bool $negative, string $expected) {
        $quiz = tqQuiz(['negative_marking' => $negative, 'negative_mark_fraction' => $negative ? '25' : null], [Question::factory(), Question::factory()]);
        [$wrong, $right] = tqPlacements($quiz);
        $attempt = tqAttempt($quiz);

        tqAnswer($attempt, $wrong, ['option_ids' => tqOptions($wrong, ['B'])])->assertOk();
        tqAnswer($attempt, $right, ['option_ids' => tqOptions($right, ['A'])])->assertOk();
        tqSubmit($attempt)->assertRedirect();

        expect(tqPoints($attempt, $wrong))->toBe($expected)
            ->and(tqPoints($attempt, $right))->toBe('1.00')
            ->and(tqEarned($attempt))->toBe($negative ? '0.75' : '1.00');
    })->with([
        'enabled' => [true, '-0.25'],
        'disabled' => [false, '0.00'],
    ]);

    it('UC-012: Given a 1-point multiple-choice question with 2 correct options of 4, When the student selects both correct and 1 wrong, Then it scores 0.50; When they select all 4, Then it scores 0', function (array $selected, string $expected) {
        $quiz = tqQuiz([], [tqMultipleChoice()]);
        [$placement] = tqPlacements($quiz);
        $attempt = tqAttempt($quiz);

        tqAnswer($attempt, $placement, ['option_ids' => tqOptions($placement, $selected)])->assertOk();
        tqSubmit($attempt);

        expect(tqPoints($attempt, $placement))->toBe($expected);
    })->with([
        'both correct and 1 wrong' => [['A', 'B', 'C'], '0.50'],
        'all 4' => [['A', 'B', 'C', 'D'], '0.00'],
        'both correct' => [['A', 'B'], '1.00'],
        'one correct' => [['A'], '0.50'],
    ]);

    it('UC-012: Given a 1-point matching question with 3 pairs and 2 matched correctly, Then it scores 0.67', function () {
        $quiz = tqQuiz([], [Question::factory()->withPairs([['France', 'Paris'], ['Greece', 'Athens'], ['Italy', 'Rome']])]);
        [$placement] = tqPlacements($quiz);
        [$france, $greece, $italy] = $placement->question->pairs->all();
        $attempt = tqAttempt($quiz);

        tqAnswer($attempt, $placement, ['matches' => [
            ['left_id' => $france->id, 'right_id' => MatchingItems::rightId($attempt, $france->id)],
            ['left_id' => $greece->id, 'right_id' => MatchingItems::rightId($attempt, $greece->id)],
            ['left_id' => $italy->id, 'right_id' => MatchingItems::rightId($attempt, $france->id)],
        ]])->assertOk();
        tqSubmit($attempt);

        expect(tqPoints($attempt, $placement))->toBe('0.67')
            ->and(tqEarned($attempt))->toBe('0.67');
    });

    it('UC-012: Given negative marking of 25%, When a multiple-choice answer earns partial credit, Then no deduction applies; When a question is left unanswered, Then it scores 0', function () {
        $quiz = tqQuiz(['negative_marking' => true, 'negative_mark_fraction' => '25'], [tqMultipleChoice(), Question::factory(), Question::factory()]);
        [$multiple, $empty, $skipped] = tqPlacements($quiz);
        $attempt = tqAttempt($quiz);

        tqAnswer($attempt, $multiple, ['option_ids' => tqOptions($multiple, ['A', 'B', 'C'])])->assertOk();
        // Saved but empty (selected, then unselected) and never touched: both unanswered.
        tqAnswer($attempt, $empty, ['option_ids' => []])->assertOk();
        tqSubmit($attempt);

        expect(tqPoints($attempt, $multiple))->toBe('0.50')
            ->and(tqPoints($attempt, $empty))->toBe('0.00')
            ->and(tqPoints($attempt, $skipped))->toBeNull()
            ->and(tqEarned($attempt))->toBe('0.50');
    });

    it('penalises an answered matching or multiple-choice answer that earns nothing, but never free text', function () {
        $quiz = tqQuiz(['negative_marking' => true, 'negative_mark_fraction' => '50'], [
            Question::factory()->matching()->state(['points' => '2.00']),
            tqMultipleChoice(),
            Question::factory()->freeText(),
            Question::factory()->state(['points' => '10.00']),
        ]);
        [$matching, $multiple, $text, $single] = tqPlacements($quiz);
        [$france, $greece] = $matching->question->pairs->all();
        $attempt = tqAttempt($quiz);

        tqAnswer($attempt, $matching, ['matches' => [
            ['left_id' => $france->id, 'right_id' => MatchingItems::rightId($attempt, $greece->id)],
        ]])->assertOk();
        tqAnswer($attempt, $multiple, ['option_ids' => tqOptions($multiple, ['A', 'C'])])->assertOk();
        tqAnswer($attempt, $text, ['text' => 'Something'])->assertOk();
        tqAnswer($attempt, $single, ['option_ids' => tqOptions($single, ['A'])])->assertOk();
        tqSubmit($attempt);

        expect(tqPoints($attempt, $matching))->toBe('-1.00')
            ->and(tqPoints($attempt, $multiple))->toBe('-0.50')
            ->and(tqPoints($attempt, $text))->toBeNull()
            ->and(tqEarned($attempt))->toBe('8.50');
    });

    it('UC-012: Given negative marking and every answer wrong, When submitted, Then earned_points is 0', function () {
        $quiz = tqQuiz(['negative_marking' => true, 'negative_mark_fraction' => '25'], [Question::factory(), Question::factory()->trueFalse(), tqMultipleChoice()]);
        [$single, $trueFalse, $multiple] = tqPlacements($quiz);
        $attempt = tqAttempt($quiz);

        tqAnswer($attempt, $single, ['option_ids' => tqOptions($single, ['B'])])->assertOk();
        tqAnswer($attempt, $trueFalse, ['option_ids' => tqOptions($trueFalse, ['false'])])->assertOk();
        tqAnswer($attempt, $multiple, ['option_ids' => tqOptions($multiple, ['C', 'D'])])->assertOk();
        tqSubmit($attempt);

        expect(tqPoints($attempt, $single))->toBe('-0.25')
            ->and(tqPoints($attempt, $trueFalse))->toBe('-0.25')
            ->and(tqPoints($attempt, $multiple))->toBe('-0.25')
            ->and(tqEarned($attempt))->toBe('0.00');
    });
});

describe('submission, grading status and result visibility', function () {
    it('UC-012: Given a quiz without free-text questions, When submitted, Then the attempt is graded; given one with a free-text question, Then it stays submitted until that answer is graded', function () {
        $auto = tqQuiz([], [Question::factory(), Question::factory()->matching()]);
        $autoAttempt = tqAttempt($auto);
        [$single] = tqPlacements($auto);
        tqAnswer($autoAttempt, $single, ['option_ids' => tqOptions($single, ['A'])]);
        tqSubmit($autoAttempt)->assertRedirect(tqUrl("quiz-attempts/{$autoAttempt->id}"));

        $withText = tqQuiz([], [Question::factory(), Question::factory()->freeText()]);
        [$choice, $text] = tqPlacements($withText);
        $textAttempt = tqAttempt($withText);
        tqAnswer($textAttempt, $choice, ['option_ids' => tqOptions($choice, ['A'])]);
        tqAnswer($textAttempt, $text, ['text' => 'An essay']);
        tqSubmit($textAttempt);

        $textAttempt->refresh();
        $textAnswer = Answer::query()->where('attempt_id', $textAttempt->id)->where('quiz_question_id', $text->id)->sole();
        $choiceAnswer = Answer::query()->where('attempt_id', $textAttempt->id)->where('quiz_question_id', $choice->id)->sole();

        expect($autoAttempt->refresh()->status)->toBe(AttemptStatus::Graded)
            ->and($autoAttempt->submitted_by)->toBe($this->student->id)
            ->and($textAttempt->status)->toBe(AttemptStatus::Submitted)
            ->and($textAnswer->grading_status)->toBe(GradingStatus::Pending)
            ->and($choiceAnswer->grading_status)->toBe(GradingStatus::AutoGraded)
            ->and(tqEarned($textAttempt))->toBe('1.00');

        // The score is shown as provisional until the free-text answer is graded.
        test()->actingAs(test()->student)->get(tqUrl("quiz-attempts/{$textAttempt->id}"))
            ->assertInertia(fn (Assert $page) => $page->where('score', ['earned' => '1.00', 'max' => '2.00', 'provisional' => true]));
    });

    it('UC-012: Given default result visibility, When a student submits, Then they see their score immediately', function () {
        // The quiz form's default (resources/js/lib/quizForm.ts) is immediate.
        $quiz = tqQuiz(['score_visibility' => ResultVisibility::Immediate], [Question::factory(), Question::factory()]);
        [$first] = tqPlacements($quiz);
        $attempt = tqAttempt($quiz);
        tqAnswer($attempt, $first, ['option_ids' => tqOptions($first, ['A'])]);

        tqSubmit($attempt)->assertRedirect();

        test()->actingAs(test()->student)->get(tqUrl("quiz-attempts/{$attempt->id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('attempt.status', 'graded')
                ->where('score', ['earned' => '1.00', 'max' => '2.00', 'provisional' => false]));
        test()->actingAs(test()->student)->get(tqUrl("quizzes/{$quiz->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page->where('attempts.0.score.earned', '1.00'));
    });

    it('hides the score when the teacher configured it so', function () {
        $quiz = tqQuiz(['score_visibility' => ResultVisibility::Hidden]);
        $attempt = tqAttempt($quiz);
        tqSubmit($attempt);

        test()->actingAs(test()->student)->get(tqUrl("quiz-attempts/{$attempt->id}"))
            ->assertInertia(fn (Assert $page) => $page->where('attempt.status', 'graded')->where('score', null));
    });

    it('changes nothing when an attempt is submitted twice', function () {
        $quiz = tqQuiz();
        $attempt = tqAttempt($quiz);
        tqSubmit($attempt);
        $before = tqSnapshot();

        tqSubmit($attempt)->assertRedirect();

        expect(tqSnapshot())->toBe($before);
    });

    it('UC-012: Given a quiz with no time limit and no deadline and an attempt in progress, When the teacher closes the activity, Then the attempt is submitted', function () {
        $quiz = tqQuiz([], [Question::factory(), Question::factory()]);
        [$first] = tqPlacements($quiz);
        $attempt = tqAttempt($quiz);
        $other = tqAttempt($quiz, $this->classmate);
        tqAnswer($attempt, $first, ['option_ids' => tqOptions($first, ['A'])]);
        expect($attempt->ends_at)->toBeNull();

        test()->actingAs($this->teacher)->post(tqUrl("activities/{$quiz->activity_id}/close"))->assertRedirect();

        expect($attempt->refresh()->status)->toBe(AttemptStatus::Graded)
            ->and($attempt->submitted_by)->toBeNull()
            ->and(tqEarned($attempt))->toBe('1.00')
            ->and($other->refresh()->status)->toBe(AttemptStatus::Graded);
    });

    it('schedules the auto-submission of due attempts every minute', function () {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'assessment:submit-due-attempts'));

        expect($events)->toHaveCount(1)
            ->and($events->first()->expression)->toBe('* * * * *');
    });

    it('auto-submits due attempts of every organisation as their own tenant', function () {
        $quiz = tqQuiz(['time_limit' => 60]);
        $attempt = tqAttempt($quiz);
        $notDue = tqAttempt(tqQuiz(['time_limit' => 600]));

        $globex = $this->createOrganization(['subdomain' => 'globex']);
        $this->travel(2)->minutes();
        // The command runs without a tenant.
        $this->withoutTenant();
        $this->artisan('assessment:submit-due-attempts')->assertSuccessful();
        $this->actingAsTenant($this->acme);

        expect($attempt->refresh()->status)->toBe(AttemptStatus::Graded)
            ->and($notDue->refresh()->status)->toBe(AttemptStatus::InProgress)
            ->and($globex->exists)->toBeTrue();
    });
});

describe('OWN-006: Given a ROLE-Student user, When they read or modify an ENT-QuizAttempt record the rule does not grant them, Then access is denied and nothing changes', function () {
    it('refuses another student\'s attempt on every endpoint', function () {
        $quiz = tqQuiz();
        [$placement] = tqPlacements($quiz);
        $attempt = tqAttempt($quiz);
        $before = tqSnapshot();

        test()->actingAs($this->classmate)->get(tqUrl("quiz-attempts/{$attempt->id}"))->assertForbidden();
        tqAnswer($attempt, $placement, ['option_ids' => tqOptions($placement, ['A'])], $this->classmate)->assertForbidden();
        tqSubmit($attempt, $this->classmate)->assertForbidden();

        expect(tqSnapshot())->toBe($before);
    });

    it('refuses quizzes of course spaces the student is not enrolled in, and drafts', function () {
        $quiz = tqQuiz();
        $draft = tqQuiz(state: 'draft');

        test()->actingAs($this->outsider)->get(tqUrl("quizzes/{$quiz->activity_id}"))->assertForbidden();
        tqStart($quiz, $this->outsider)->assertForbidden();
        tqStart($draft)->assertForbidden();

        expect(QuizAttempt::query()->count())->toBe(0);
    });

    it('lists only the student\'s own attempts', function () {
        $quiz = tqQuiz(['max_attempts' => 3]);
        tqAttempt($quiz, $this->classmate);
        $own = tqAttempt($quiz);

        test()->actingAs($this->student)->get(tqUrl("quizzes/{$quiz->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page->has('attempts', 1)->where('attempts.0.id', $own->id));
    });
});

describe('OWN-006: Given a ROLE-Teacher user, When they read or modify an ENT-QuizAttempt record the rule does not grant them, Then access is denied and nothing changes', function () {
    it('never lets a teacher take, answer or submit for a student', function (string $teacher) {
        $quiz = tqQuiz();
        [$placement] = tqPlacements($quiz);
        $attempt = tqAttempt($quiz);
        $actor = $this->{$teacher};
        $before = tqSnapshot();

        tqStart($quiz, $actor)->assertForbidden();
        tqAnswer($attempt, $placement, ['option_ids' => tqOptions($placement, ['A'])], $actor)->assertForbidden();
        tqSubmit($attempt, $actor)->assertForbidden();

        expect(tqSnapshot())->toBe($before);
    })->with(['teacher', 'otherTeacher']);

    it('refuses a teacher of another course space any access to the attempt', function () {
        $attempt = tqAttempt(tqQuiz());
        $before = tqSnapshot();

        test()->actingAs($this->otherTeacher)->get(tqUrl("quiz-attempts/{$attempt->id}"))->assertForbidden();

        expect(tqSnapshot())->toBe($before);
    });
});

describe('OWN-006: Given a ROLE-OrgAdmin user, When they read or modify an ENT-QuizAttempt record the rule does not grant them, Then access is denied and nothing changes', function () {
    it('never lets an org admin read or change an individual attempt', function () {
        $quiz = tqQuiz();
        $attempt = tqAttempt($quiz);
        $before = tqSnapshot();

        test()->actingAs($this->admin)->get(tqUrl("quizzes/{$quiz->activity_id}"))->assertForbidden();
        test()->actingAs($this->admin)->get(tqUrl("quiz-attempts/{$attempt->id}"))->assertForbidden();
        tqStart($quiz, $this->admin)->assertForbidden();
        tqSubmit($attempt, $this->admin)->assertForbidden();

        expect(tqSnapshot())->toBe($before);
    });
});

describe('OWN-007: Given a ROLE-Student user, When they read or modify an ENT-Answer record the rule does not grant them, Then access is denied and nothing changes', function () {
    it('never shows or changes another student\'s answers', function () {
        $quiz = tqQuiz([], [Question::factory()->freeText()]);
        [$placement] = tqPlacements($quiz);
        $attempt = tqAttempt($quiz);
        tqAnswer($attempt, $placement, ['text' => 'My own words'])->assertOk();
        $before = tqSnapshot();

        tqAnswer($attempt, $placement, ['text' => 'Overwritten'], $this->classmate)->assertForbidden()
            ->assertHeader('Content-Type', 'application/problem+json');
        test()->actingAs($this->classmate)->get(tqUrl("quiz-attempts/{$attempt->id}"))->assertForbidden();

        expect(tqSnapshot())->toBe($before);
    });
});

describe('OWN-007: Given a ROLE-Teacher user, When they read or modify an ENT-Answer record the rule does not grant them, Then access is denied and nothing changes', function () {
    it('never lets a teacher give or change a student\'s answer', function (string $teacher) {
        $quiz = tqQuiz([], [Question::factory()->freeText()]);
        [$placement] = tqPlacements($quiz);
        $attempt = tqAttempt($quiz);
        tqAnswer($attempt, $placement, ['text' => 'Student text']);
        $before = tqSnapshot();

        tqAnswer($attempt, $placement, ['text' => 'Teacher text'], $this->{$teacher})->assertForbidden();

        expect(tqSnapshot())->toBe($before);
    })->with(['teacher', 'otherTeacher']);
});

describe('OWN-007: Given a ROLE-OrgAdmin user, When they read or modify an ENT-Answer record the rule does not grant them, Then access is denied and nothing changes', function () {
    it('never lets an org admin give or change an answer', function () {
        $quiz = tqQuiz([], [Question::factory()->freeText()]);
        [$placement] = tqPlacements($quiz);
        $attempt = tqAttempt($quiz);
        tqAnswer($attempt, $placement, ['text' => 'Student text']);
        $before = tqSnapshot();

        tqAnswer($attempt, $placement, ['text' => 'Admin text'], $this->admin)->assertForbidden();

        expect(tqSnapshot())->toBe($before);
    });
});

describe('tenant isolation', function () {
    it('does not find attempts or quizzes of another organisation', function () {
        $quiz = tqQuiz();
        [$placement] = tqPlacements($quiz);
        $attempt = tqAttempt($quiz);
        $before = tqSnapshot();

        $globex = $this->createOrganization(['subdomain' => 'globex']);
        $this->actingAsTenant($globex);
        $foreign = User::factory()->student()->create();
        $url = fn (string $path) => $this->tenantUrl($globex, $path);

        $this->actingAs($foreign)->get($url("quiz-attempts/{$attempt->id}"))->assertNotFound();
        $this->actingAs($foreign)->putJson($url("quiz-attempts/{$attempt->id}/answers/{$placement->id}"), ['option_ids' => []])->assertNotFound();
        $this->actingAs($foreign)->post($url("quizzes/{$quiz->activity_id}/attempts"))->assertNotFound();

        expect(tqSnapshot())->toBe($before);
    });
});

describe('the activity page', function () {
    it('offers the quiz to enrolled students only', function () {
        $quiz = tqQuiz();

        test()->actingAs($this->student)->get(tqUrl("activities/{$quiz->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page->where('can.participate', true));
        test()->actingAs($this->teacher)->get(tqUrl("activities/{$quiz->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page->where('can.participate', false));
    });
});

it('keeps matching right ids stable per attempt and different between attempts', function () {
    $quiz = tqQuiz(['max_attempts' => 2], [Question::factory()->matching()]);
    [$placement] = tqPlacements($quiz);
    $pair = $placement->question->pairs->first();
    $first = tqAttempt($quiz);
    tqSubmit($first);
    $second = tqAttempt($quiz);

    expect(MatchingItems::rightId($first, $pair->id))->toBe(MatchingItems::rightId($first, $pair->id))
        ->and(MatchingItems::rightId($first, $pair->id))->not->toBe(MatchingItems::rightId($second, $pair->id))
        ->and(MatchingPair::query()->count())->toBe(2);
});

<?php

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Database\Factories\QuestionFactory;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\GradingStatus;
use App\Modules\Assessment\Enums\ResultVisibility;
use App\Modules\Assessment\Events\GradeFinalised;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Enums\GradingScale;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-026 grading of free-text answers with feedback, provisional vs final
| scores and GradeFinalised (SCOPE-024); UC-017 My results on the
| organisation's grading scale (DATA-003, DATA-005); UC-028 quiz
| submissions in the overview; OWN-006 / OWN-007 on every new endpoint.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

    $this->acme = $this->createOrganization(['subdomain' => 'acme', 'grading_scale' => GradingScale::Scale0To10]);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create(['name' => 'Eleni Student']);
    $this->classmate = User::factory()->student()->create(['name' => 'Nikos Classmate']);
    $this->third = User::factory()->student()->create(['name' => 'Maria Third']);
    $this->admin = User::factory()->orgAdmin()->create();

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();

    foreach ([$this->student, $this->classmate, $this->third] as $enrolled) {
        Enrolment::factory()->for($this->space, 'courseSpace')->for($enrolled, 'student')->create();
    }
});

function grUrl(string $path): string
{
    return test()->tenantUrl(test()->acme, ltrim($path, '/'));
}

/**
 * A published self-paced quiz of the course space with the given
 * questions (question factories, each placed once, in order).
 *
 * @param  list<QuestionFactory>  $questions
 * @param  array<string, mixed>  $settings
 */
function grQuiz(array $questions, array $settings = [], ?CourseSpace $space = null): Quiz
{
    $quiz = Quiz::factory()->for(Activity::factory()->for($space ?? test()->space, 'courseSpace')->published()->create(), 'activity');

    foreach ($questions as $question) {
        $quiz = $quiz->withQuestions(1, $question);
    }

    return $quiz->create($settings);
}

function grFreeText(string $points): QuestionFactory
{
    return Question::factory()->freeText()->state(['points' => $points]);
}

/**
 * @return list<QuizQuestion>
 */
function grPlacements(Quiz $quiz): array
{
    return $quiz->quizQuestions()->with(['question.options', 'question.pairs'])->get()->all();
}

/**
 * Starts an attempt, gives the answers (placement => payload) and submits.
 *
 * @param  array<int, array<string, mixed>>  $answers  by placement index
 */
function grSubmitted(Quiz $quiz, User $student, array $answers): QuizAttempt
{
    test()->actingAs($student)->post(grUrl("quizzes/{$quiz->activity_id}/attempts"))->assertRedirect();
    $attempt = QuizAttempt::query()->where('quiz_id', $quiz->activity_id)->where('student_id', $student->id)
        ->where('status', AttemptStatus::InProgress->value)->sole();
    $placements = grPlacements($quiz);

    foreach ($answers as $index => $payload) {
        test()->actingAs($student)
            ->putJson(grUrl("quiz-attempts/{$attempt->id}/answers/{$placements[$index]->id}"), $payload)
            ->assertOk();
    }

    test()->actingAs($student)->post(grUrl("quiz-attempts/{$attempt->id}/submit"))->assertRedirect();

    return $attempt->refresh();
}

function grAnswer(QuizAttempt $attempt, QuizQuestion $placement): Answer
{
    return Answer::query()->where('attempt_id', $attempt->id)->where('quiz_question_id', $placement->id)->sole();
}

/**
 * @param  array<string, mixed>  $data
 */
function grGrade(Answer $answer, array $data, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->teacher)->put(grUrl("grading/answers/{$answer->id}"), $data);
}

/**
 * @return array<string, mixed>
 */
function grSnapshot(): array
{
    return [
        'attempts' => QuizAttempt::query()->withoutGlobalScopes()->orderBy('id')->get()->makeVisible('earned_points')->toArray(),
        'answers' => Answer::query()->withoutGlobalScopes()->orderBy('id')->get()->makeVisible(['text_response', 'feedback'])->toArray(),
    ];
}

describe('UC-017: My results', function () {
    it('UC-017: Given a 0-10 scale and a score of 7 out of 8 points, Then the result shows 8.8', function () {
        $quiz = grQuiz([grFreeText('8.00')]);
        [$text] = grPlacements($quiz);
        $attempt = grSubmitted($quiz, $this->student, [['text' => 'My essay']]);
        grGrade(grAnswer($attempt, $text), ['points' => '7'])->assertRedirect(grUrl("grading/attempts/{$attempt->id}"));

        $this->actingAs($this->student)->get(grUrl('results'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Assessment/Results/Index')
                ->has('results', 1)
                ->where('results.0.activity_id', $quiz->activity_id)
                ->where('results.0.attempts.0.id', $attempt->id)
                ->where('results.0.attempts.0.final', true)
                ->where('results.0.attempts.0.score.earned', '7.00')
                ->where('results.0.attempts.0.score.max', '8.00')
                ->where('results.0.attempts.0.score.grade.scale', 'scale_0_10')
                ->where('results.0.attempts.0.score.grade.value', '8.8'));

        $this->actingAs($this->student)->get(grUrl("results/{$attempt->id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Assessment/Results/Show')
                ->where('score.grade.value', '8.8')
                ->where('attempt.final', true));
    });

    it('converts to the 0-100 scale and judges the pass mark on the points scale', function (GradingScale $scale, string $value, ?bool $passed) {
        $this->acme->update(['grading_scale' => $scale]);
        $quiz = grQuiz([grFreeText('8.00')], ['pass_mark_percentage' => '90.00']);
        [$text] = grPlacements($quiz);
        $attempt = grSubmitted($quiz, $this->student, [['text' => 'My essay']]);
        grGrade(grAnswer($attempt, $text), ['points' => '7']);

        $this->actingAs($this->student)->get(grUrl("results/{$attempt->id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('score.grade.value', $value)
                ->where('score.grade.percentage', '87.5')
                ->where('score.grade.passed', $passed));
    })->with([
        '0-100' => [GradingScale::Scale0To100, '87.5', false],
        'points with pass mark' => [GradingScale::PointsPassMark, '7.00', false],
    ]);

    it('UC-017: Given a quiz with ungraded free-text answers, Then the result is marked provisional', function () {
        $quiz = grQuiz([Question::factory(), grFreeText('1.00')]);
        $attempt = grSubmitted($quiz, $this->student, [
            ['option_ids' => grPlacements($quiz)[0]->question->options->where('text', 'A')->pluck('id')->all()],
            ['text' => 'An essay'],
        ]);

        expect($attempt->status)->toBe(AttemptStatus::Submitted);

        $this->actingAs($this->student)->get(grUrl('results'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('results.0.attempts.0.final', false)
                ->where('results.0.attempts.0.score.provisional', true));

        $this->actingAs($this->student)->get(grUrl("results/{$attempt->id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('attempt.final', false)
                ->where('score.provisional', true)
                ->where('questions.1.grading_status', 'pending')
                ->where('questions.1.points_awarded', null));
    });

    it('UC-017: Given a student, When they request another student\'s result, Then it is refused', function () {
        $quiz = grQuiz([grFreeText('1.00')]);
        $attempt = grSubmitted($quiz, $this->classmate, [['text' => 'Classmate essay']]);

        $this->actingAs($this->student)->get(grUrl("results/{$attempt->id}"))->assertForbidden();
        // Their own list holds only their own results.
        $this->actingAs($this->student)->get(grUrl('results'))
            ->assertInertia(fn (Assert $page) => $page->has('results', 0));
    });

    it('shows teacher feedback, and correct answers only when the quiz allows it', function (ResultVisibility $visibility, bool $shown) {
        $quiz = grQuiz([Question::factory(), grFreeText('1.00')], ['answers_visibility' => $visibility]);
        [$choice, $text] = grPlacements($quiz);
        $attempt = grSubmitted($quiz, $this->student, [
            ['option_ids' => $choice->question->options->where('text', 'B')->pluck('id')->all()],
            ['text' => 'An essay'],
        ]);
        grGrade(grAnswer($attempt, $text), ['points' => '0.5', 'feedback' => 'Good start']);

        $this->actingAs($this->student)->get(grUrl("results/{$attempt->id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('answersVisible', $shown)
                ->where('questions.0.options.0.text', 'A')
                ->where('questions.0.options.0.correct', $shown ? true : null)
                ->where('questions.0.options.1.selected', true)
                ->where('questions.1.text', 'An essay')
                ->where('questions.1.feedback', 'Good start')
                ->where('questions.1.points_awarded', '0.50'));

        $this->actingAs($this->student)->get(grUrl('results'))
            ->assertInertia(fn (Assert $page) => $page->where('results.0.attempts.0.has_feedback', true));
    })->with([
        'immediate' => [ResultVisibility::Immediate, true],
        'hidden' => [ResultVisibility::Hidden, false],
    ]);

    it('hides the score and the points when the quiz hides the score', function () {
        $quiz = grQuiz([Question::factory()], ['score_visibility' => ResultVisibility::Hidden]);
        $attempt = grSubmitted($quiz, $this->student, [
            ['option_ids' => grPlacements($quiz)[0]->question->options->where('text', 'A')->pluck('id')->all()],
        ]);

        $this->actingAs($this->student)->get(grUrl('results'))
            ->assertInertia(fn (Assert $page) => $page->where('results.0.attempts.0.score', null));
        $this->actingAs($this->student)->get(grUrl("results/{$attempt->id}"))
            ->assertInertia(fn (Assert $page) => $page->where('score', null)->where('questions.0.points_awarded', null));
    });

    it('lists no attempt in progress and opens it for taking instead of as a result', function () {
        $quiz = grQuiz([Question::factory()]);
        $this->actingAs($this->student)->post(grUrl("quizzes/{$quiz->activity_id}/attempts"));
        $attempt = QuizAttempt::query()->where('student_id', $this->student->id)->sole();

        $this->actingAs($this->student)->get(grUrl('results'))
            ->assertInertia(fn (Assert $page) => $page->has('results', 0));
        $this->actingAs($this->student)->get(grUrl("results/{$attempt->id}"))
            ->assertRedirect(grUrl("quiz-attempts/{$attempt->id}"));
    });

    it('is a student page only', function () {
        $this->actingAs($this->teacher)->get(grUrl('results'))->assertForbidden();
        $this->actingAs($this->admin)->get(grUrl('results'))->assertForbidden();
    });
});

describe('UC-026: grading free-text answers', function () {
    it('UC-026: Given a 5-point free-text question, When the teacher enters 6, Then it is rejected', function () {
        $quiz = grQuiz([grFreeText('5.00')]);
        [$text] = grPlacements($quiz);
        $attempt = grSubmitted($quiz, $this->student, [['text' => 'An essay']]);
        $before = grSnapshot();

        grGrade(grAnswer($attempt, $text), ['points' => '6'])->assertSessionHasErrors('points');

        expect(grSnapshot())->toBe($before)
            ->and(grAnswer($attempt, $text)->grading_status)->toBe(GradingStatus::Pending);

        // The bounds are inclusive; malformed points are refused too.
        foreach (['-1', '5.001', 'five', ''] as $invalid) {
            grGrade(grAnswer($attempt, $text), ['points' => $invalid])->assertSessionHasErrors('points');
        }
        expect(grSnapshot())->toBe($before);

        grGrade(grAnswer($attempt, $text), ['points' => '5'])->assertSessionHasNoErrors();
        expect(grAnswer($attempt, $text)->points_awarded)->toBe('5.00');
    });

    it('UC-026: Given an attempt with 2 free-text answers and 1 graded, Then the attempt remains provisional; When the 2nd is graded, Then it becomes final', function () {
        Event::fake([GradeFinalised::class]);
        $quiz = grQuiz([Question::factory(), grFreeText('5.00'), grFreeText('5.00')]);
        [$choice, $first, $second] = grPlacements($quiz);
        $attempt = grSubmitted($quiz, $this->student, [
            ['option_ids' => $choice->question->options->where('text', 'A')->pluck('id')->all()],
            ['text' => 'First essay'],
            ['text' => 'Second essay'],
        ]);

        grGrade(grAnswer($attempt, $first), ['points' => '3.5', 'feedback' => '  Clear argument  '])->assertSessionHasNoErrors();

        $graded = grAnswer($attempt, $first);
        expect($attempt->refresh()->status)->toBe(AttemptStatus::Submitted)
            ->and($attempt->getRawOriginal('earned_points'))->toBe('4.50')
            ->and($graded->grading_status)->toBe(GradingStatus::Graded)
            ->and($graded->feedback)->toBe('Clear argument')
            ->and($graded->graded_by)->toBe($this->teacher->id)
            ->and($graded->graded_at)->not->toBeNull();
        Event::assertNotDispatched(GradeFinalised::class);

        $this->actingAs($this->teacher)->get(grUrl("grading/attempts/{$attempt->id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Assessment/Grading/Attempt')
                ->where('attempt.final', false)
                ->where('score.provisional', true));

        grGrade(grAnswer($attempt, $second), ['points' => '2'])->assertSessionHasNoErrors();

        expect($attempt->refresh()->status)->toBe(AttemptStatus::Graded)
            ->and($attempt->getRawOriginal('earned_points'))->toBe('6.50');
        Event::assertDispatchedTimes(GradeFinalised::class, 1);
        Event::assertDispatched(GradeFinalised::class, fn (GradeFinalised $e): bool => $e->attemptId === $attempt->id && $e->organizationId === $this->acme->id);

        $this->actingAs($this->student)->get(grUrl("results/{$attempt->id}"))
            ->assertInertia(fn (Assert $page) => $page->where('attempt.final', true)->where('score.provisional', false));
    });

    it('lets the teacher regrade an auto-graded answer and correct a graded one; the total is recalculated', function () {
        Event::fake([GradeFinalised::class]);
        $quiz = grQuiz([Question::factory()->state(['points' => '2.00'])]);
        [$choice] = grPlacements($quiz);
        $attempt = grSubmitted($quiz, $this->student, [
            ['option_ids' => $choice->question->options->where('text', 'B')->pluck('id')->all()],
        ]);
        // Fully auto-graded: final at submission.
        expect($attempt->status)->toBe(AttemptStatus::Graded);
        Event::assertDispatchedTimes(GradeFinalised::class, 1);

        grGrade(grAnswer($attempt, $choice), ['points' => '1.5'])->assertSessionHasNoErrors();
        expect(grAnswer($attempt, $choice)->grading_status)->toBe(GradingStatus::Graded)
            ->and($attempt->refresh()->getRawOriginal('earned_points'))->toBe('1.50');

        grGrade(grAnswer($attempt, $choice), ['points' => '1', 'feedback' => ''])->assertSessionHasNoErrors();
        expect(grAnswer($attempt, $choice)->grading_status)->toBe(GradingStatus::Graded)
            ->and(grAnswer($attempt, $choice)->feedback)->toBeNull()
            ->and($attempt->refresh()->status)->toBe(AttemptStatus::Graded)
            ->and($attempt->getRawOriginal('earned_points'))->toBe('1.00');
        // Still the one finalisation of the submission.
        Event::assertDispatchedTimes(GradeFinalised::class, 1);
    });

    it('UC-026: Given a teacher, When they grade an answer of another teacher\'s activity, Then it is refused', function () {
        $quiz = grQuiz([grFreeText('5.00')]);
        [$text] = grPlacements($quiz);
        $attempt = grSubmitted($quiz, $this->student, [['text' => 'An essay']]);
        $before = grSnapshot();

        grGrade(grAnswer($attempt, $text), ['points' => '5', 'feedback' => 'Hijack'], $this->otherTeacher)->assertForbidden();
        $this->actingAs($this->otherTeacher)->get(grUrl("grading/attempts/{$attempt->id}"))->assertForbidden();

        expect(grSnapshot())->toBe($before);
    });

    it('shows the owning teacher every answer with the correct answers and the score on the scale', function () {
        $quiz = grQuiz([Question::factory(), grFreeText('1.00')]);
        [$choice] = grPlacements($quiz);
        $attempt = grSubmitted($quiz, $this->student, [
            ['option_ids' => $choice->question->options->where('text', 'A')->pluck('id')->all()],
            ['text' => 'An essay'],
        ]);

        $this->actingAs($this->teacher)->get(grUrl("grading/attempts/{$attempt->id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('attempt.student', 'Eleni Student')
                ->where('canGrade', true)
                ->where('score.earned', '1.00')
                ->where('score.grade.value', '5.0')
                ->where('questions.0.options.0.correct', true)
                ->where('questions.0.points_awarded', '1.00')
                ->where('questions.1.text', 'An essay')
                ->where('questions.1.grading_status', 'pending'));
    });
});

describe('UC-028: quiz submissions in the overview', function () {
    beforeEach(function () {
        $this->quiz = grQuiz([Question::factory(), grFreeText('2.00')]);
        $a = $this->quiz->quizQuestions()->first()->question->options->where('text', 'A')->pluck('id')->all();

        // Free text left unanswered: nothing to grade, final at submission.
        $this->graded = grSubmitted($this->quiz, $this->student, [['option_ids' => $a]]);
        $this->travel(1)->minutes();
        $this->needsGrading = grSubmitted($this->quiz, $this->classmate, [['option_ids' => $a], ['text' => 'Essay']]);
        $this->travel(1)->minutes();
        $this->alsoGraded = grSubmitted($this->quiz, $this->third, []);
    });

    it('UC-028: Given an activity with 3 submissions of which 1 needs grading, When filtered by needs grading, Then exactly 1 is listed', function () {
        $this->actingAs($this->teacher)->get(grUrl("activities/{$this->quiz->activity_id}/submissions?status=needs_grading"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Activities/Submissions')
                ->has('submissions', 1)
                ->where('submissions.0.id', $this->needsGrading->id)
                ->where('submissions.0.participant', 'Nikos Classmate')
                ->where('submissions.0.status', 'needs_grading')
                ->where('submissions.0.earned_points', '1.00')
                ->where('submissions.0.max_points', '3.00')
                ->where('submissions.0.url', grUrl("grading/attempts/{$this->needsGrading->id}")));

        $this->actingAs($this->teacher)->get(grUrl("activities/{$this->quiz->activity_id}/submissions"))
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 3)
                ->where('submissions.0.id', $this->alsoGraded->id)
                ->where('submissions.2.id', $this->graded->id));

        $this->actingAs($this->teacher)->get(grUrl("activities/{$this->quiz->activity_id}/submissions?status=graded"))
            ->assertInertia(fn (Assert $page) => $page->has('submissions', 2));
    });

    it('does not list attempts still in progress', function () {
        $other = User::factory()->student()->create();
        Enrolment::factory()->for($this->space, 'courseSpace')->for($other, 'student')->create();
        $this->actingAs($other)->post(grUrl("quizzes/{$this->quiz->activity_id}/attempts"));

        $this->actingAs($this->teacher)->get(grUrl("activities/{$this->quiz->activity_id}/submissions"))
            ->assertInertia(fn (Assert $page) => $page->has('submissions', 3));
    });

    it('UC-028: Given a teacher, When they open submissions of another teacher\'s activity, Then it is refused', function () {
        $response = $this->actingAs($this->otherTeacher)->get(grUrl("activities/{$this->quiz->activity_id}/submissions"));

        expect($response->status())->toBeIn([403, 404]);
        $this->actingAs($this->otherTeacher)->get(grUrl("grading/attempts/{$this->needsGrading->id}"))->assertForbidden();
    });
});

describe('OWN-006 on the result and grading pages', function () {
    beforeEach(function () {
        $this->quiz = grQuiz([grFreeText('2.00')]);
        $this->attempt = grSubmitted($this->quiz, $this->student, [['text' => 'My essay']]);
        $this->answer = grAnswer($this->attempt, grPlacements($this->quiz)[0]);
    });

    it('OWN-006: Given a ROLE-Student user, When they read or modify an ENT-QuizAttempt record the rule does not grant them, Then access is denied and nothing changes', function () {
        $before = grSnapshot();

        $this->actingAs($this->classmate)->get(grUrl("results/{$this->attempt->id}"))->assertForbidden();
        $this->actingAs($this->classmate)->get(grUrl("grading/attempts/{$this->attempt->id}"))->assertForbidden();
        // Not even their own attempt is theirs to grade.
        $this->actingAs($this->student)->get(grUrl("grading/attempts/{$this->attempt->id}"))->assertForbidden();
        $this->actingAs($this->student)->get(grUrl("results/{$this->attempt->id}"))->assertOk();

        expect(grSnapshot())->toBe($before);
    });

    it('OWN-006: Given a ROLE-Teacher user, When they read or modify an ENT-QuizAttempt record the rule does not grant them, Then access is denied and nothing changes', function () {
        $before = grSnapshot();

        $this->actingAs($this->otherTeacher)->get(grUrl("grading/attempts/{$this->attempt->id}"))->assertForbidden();
        // Results pages are the student's own, also for the owning teacher.
        $this->actingAs($this->teacher)->get(grUrl("results/{$this->attempt->id}"))->assertForbidden();
        $this->actingAs($this->teacher)->get(grUrl("grading/attempts/{$this->attempt->id}"))->assertOk();

        expect(grSnapshot())->toBe($before);
    });

    it('OWN-006: Given a ROLE-OrgAdmin user, When they read or modify an ENT-QuizAttempt record the rule does not grant them, Then access is denied and nothing changes', function () {
        $before = grSnapshot();

        $this->actingAs($this->admin)->get(grUrl("results/{$this->attempt->id}"))->assertForbidden();
        $this->actingAs($this->admin)->get(grUrl("grading/attempts/{$this->attempt->id}"))->assertForbidden();
        $this->actingAs($this->admin)->get(grUrl('results'))->assertForbidden();

        expect(grSnapshot())->toBe($before);
    });

    it('binds no attempt of another organisation', function () {
        $globex = $this->createOrganization(['subdomain' => 'globex']);
        $this->actingAsTenant($globex);
        $foreignTeacher = User::factory()->teacher()->create();

        $this->actingAs($foreignTeacher)
            ->get($this->tenantUrl($globex, "grading/attempts/{$this->attempt->id}"))
            ->assertNotFound();
        $this->actingAs($foreignTeacher)
            ->put($this->tenantUrl($globex, "grading/answers/{$this->answer->id}"), ['points' => '1'])
            ->assertNotFound();
    });
});

describe('OWN-007 on grading', function () {
    beforeEach(function () {
        $this->quiz = grQuiz([grFreeText('2.00')]);
        $this->attempt = grSubmitted($this->quiz, $this->student, [['text' => 'My essay']]);
        $this->answer = grAnswer($this->attempt, grPlacements($this->quiz)[0]);
    });

    it('OWN-007: Given a ROLE-Student user, When they read or modify an ENT-Answer record the rule does not grant them, Then access is denied and nothing changes', function () {
        $before = grSnapshot();

        grGrade($this->answer, ['points' => '2'], $this->student)->assertForbidden();
        grGrade($this->answer, ['points' => '2'], $this->classmate)->assertForbidden();

        expect(grSnapshot())->toBe($before);
    });

    it('OWN-007: Given a ROLE-Teacher user, When they read or modify an ENT-Answer record the rule does not grant them, Then access is denied and nothing changes', function () {
        $before = grSnapshot();

        grGrade($this->answer, ['points' => '2'], $this->otherTeacher)->assertForbidden();

        expect(grSnapshot())->toBe($before);
    });

    it('OWN-007: Given a ROLE-OrgAdmin user, When they read or modify an ENT-Answer record the rule does not grant them, Then access is denied and nothing changes', function () {
        $before = grSnapshot();

        grGrade($this->answer, ['points' => '2'], $this->admin)->assertForbidden();

        expect(grSnapshot())->toBe($before);
    });
});

describe('state models on grading', function () {
    it('ENT-QuizAttempt: Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged', function () {
        // Grading an answer of an attempt still in progress would skip submission.
        $quiz = grQuiz([grFreeText('2.00')]);
        $this->actingAs($this->student)->post(grUrl("quizzes/{$quiz->activity_id}/attempts"));
        $attempt = QuizAttempt::query()->where('student_id', $this->student->id)->sole();
        $placement = grPlacements($quiz)[0];
        $this->actingAs($this->student)->putJson(grUrl("quiz-attempts/{$attempt->id}/answers/{$placement->id}"), ['text' => 'Draft'])->assertOk();
        $before = grSnapshot();

        grGrade(grAnswer($attempt, $placement), ['points' => '2'])->assertSessionHasErrors('points');

        expect(grSnapshot())->toBe($before)
            ->and($attempt->refresh()->status)->toBe(AttemptStatus::InProgress);

        $this->actingAs($this->teacher)->get(grUrl("grading/attempts/{$attempt->id}"))
            ->assertInertia(fn (Assert $page) => $page->where('canGrade', false)->where('score', null));
    });

    it('ENT-Answer: Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged', function () {
        $quiz = grQuiz([grFreeText('2.00')]);
        $this->actingAs($this->student)->post(grUrl("quizzes/{$quiz->activity_id}/attempts"));
        $attempt = QuizAttempt::query()->where('student_id', $this->student->id)->sole();
        $placement = grPlacements($quiz)[0];
        $this->actingAs($this->student)->putJson(grUrl("quiz-attempts/{$attempt->id}/answers/{$placement->id}"), ['text' => 'Draft'])->assertOk();

        // A pending answer of an unsubmitted attempt stays pending.
        grGrade(grAnswer($attempt, $placement), ['points' => '1']);
        expect(grAnswer($attempt, $placement)->grading_status)->toBe(GradingStatus::Pending);

        // After grading, a graded answer stays graded when corrected.
        $this->actingAs($this->student)->post(grUrl("quiz-attempts/{$attempt->id}/submit"));
        grGrade(grAnswer($attempt, $placement), ['points' => '1']);
        grGrade(grAnswer($attempt, $placement), ['points' => '2']);

        expect(grAnswer($attempt, $placement)->grading_status)->toBe(GradingStatus::Graded)
            ->and(grAnswer($attempt, $placement)->points_awarded)->toBe('2.00');
    });

    it('keeps answers, feedback and points out of validation errors', function () {
        $quiz = grQuiz([grFreeText('5.00')]);
        $attempt = grSubmitted($quiz, $this->student, [['text' => 'Secret essay']]);

        $response = grGrade(grAnswer($attempt, grPlacements($quiz)[0]), ['points' => '6.25', 'feedback' => 'Secret feedback']);

        $bag = session('errors');
        $errors = (string) json_encode($bag instanceof ViewErrorBag ? $bag->getBag('default')->toArray() : $bag);
        expect($errors)->not->toContain('Secret')->not->toContain('6.25');
        $response->assertSessionHasErrors('points');
    });
});

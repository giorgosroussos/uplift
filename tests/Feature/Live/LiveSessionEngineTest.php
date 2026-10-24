<?php

use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Assessment\Actions\ListMyResults;
use App\Modules\Assessment\Database\Factories\QuestionFactory;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use App\Modules\Live\Actions\FinishLiveSession;
use App\Modules\Live\Actions\ShowLiveParticipation;
use App\Modules\Live\Actions\ShowLiveProgress;
use App\Modules\Live\Actions\ShowLiveResults;
use App\Modules\Live\Enums\LiveSessionStatus;
use App\Modules\Live\Events\LiveSessionFinished;
use App\Modules\Live\Jobs\FinishLiveSessionJob;
use App\Modules\Live\LiveServiceProvider;
use App\Modules\Live\Models\LiveAnswer;
use App\Modules\Live\Models\LiveParticipant;
use App\Modules\Live\Models\LiveSession;
use App\Modules\Live\Support\LiveMatchingItems;
use App\Modules\Live\Support\LiveQuizType;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

/*
| P5-T2: the live session engine (UC-024, UC-025). Lobby, manual or
| scheduled start, one common end time enforced on the server
| (SCOPE-021), answers over HTTP, early and timed finish, scoring with
| partial credit and no negative marking (SCOPE-022), final ranks and
| LiveSessionFinished.
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

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->classmate, 'student')->create();
});

function lvUrl(string $path): string
{
    return test()->tenantUrl(test()->acme, ltrim($path, '/'));
}

/**
 * A published live quiz (15 minutes, manual start unless a scheduled
 * start is given) of the course space with the given questions.
 *
 * @param  list<QuestionFactory>  $questions
 */
function lvQuiz(array $questions = [], ?CarbonImmutable $scheduledAt = null, string $state = 'published'): Quiz
{
    $activity = Activity::factory()->for(test()->space, 'courseSpace');
    $activity = $state === 'published' ? $activity->published() : $activity;

    $quiz = Quiz::factory()->for($activity->create(), 'activity');
    $quiz = $scheduledAt === null ? $quiz->live() : $quiz->scheduled($scheduledAt);

    foreach ($questions === [] ? [Question::factory()] : $questions as $question) {
        $quiz = $quiz->withQuestions(1, $question);
    }

    return $quiz->create();
}

/**
 * @return list<QuizQuestion>
 */
function lvPlacements(Quiz $quiz): array
{
    return $quiz->quizQuestions()->with(['question.options', 'question.pairs'])->get()->all();
}

/**
 * @param  list<string>  $texts
 * @return list<string>
 */
function lvOptions(QuizQuestion $placement, array $texts): array
{
    return $placement->question->options->whereIn('text', $texts)->pluck('id')->values()->all();
}

function lvOpen(Quiz $quiz, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->teacher)->post(lvUrl("live/quizzes/{$quiz->activity_id}/lobby"));
}

function lvSession(Quiz $quiz): LiveSession
{
    return LiveSession::query()->where('quiz_id', $quiz->activity_id)->where('status', '<>', 'cancelled')->sole();
}

function lvStart(LiveSession $session, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->teacher)->post(lvUrl("live/sessions/{$session->id}/start"));
}

function lvJoin(Quiz $quiz, ?User $student = null): TestResponse
{
    return test()->actingAs($student ?? test()->student)->post(lvUrl("live/quizzes/{$quiz->activity_id}/join"));
}

function lvParticipant(Quiz $quiz, ?User $student = null): LiveParticipant
{
    return LiveParticipant::query()
        ->where('live_session_id', lvSession($quiz)->id)
        ->where('student_id', ($student ?? test()->student)->id)
        ->sole();
}

/**
 * @param  array<string, mixed>  $data
 */
function lvAnswer(LiveParticipant $participant, QuizQuestion $placement, array $data, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->student)
        ->putJson(lvUrl("live/participants/{$participant->id}/answers/{$placement->id}"), $data);
}

function lvSubmit(LiveParticipant $participant, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->student)->post(lvUrl("live/participants/{$participant->id}/submit"));
}

/**
 * Opens the lobby, lets the given students join and starts the quiz now.
 *
 * @param  list<User>  $students
 */
function lvRun(Quiz $quiz, array $students = []): LiveSession
{
    lvOpen($quiz)->assertSessionHasNoErrors();

    foreach ($students === [] ? [test()->student] : $students as $student) {
        lvJoin($quiz, $student)->assertSessionHasNoErrors();
    }

    lvStart(lvSession($quiz))->assertSessionHasNoErrors();

    return lvSession($quiz)->refresh();
}

/**
 * A new student enrolled in the course space.
 */
function lvEnrolledStudent(): User
{
    $student = User::factory()->student()->create();
    Enrolment::factory()->for(test()->space, 'courseSpace')->for($student, 'student')->create();

    return $student;
}

describe('starting', function () {
    it('fixes the end time at start + duration, the same for every student (UC-024)', function () {
        $quiz = lvQuiz();
        $session = lvRun($quiz, [$this->student, $this->classmate]);

        expect($session->status)->toBe(LiveSessionStatus::Running)
            ->and($session->started_at->toDateTimeString())->toBe('2026-11-02 10:00:00')
            ->and($session->ends_at->toDateTimeString())->toBe('2026-11-02 10:15:00');

        foreach ([$this->student, $this->classmate] as $student) {
            $view = app(ShowLiveParticipation::class)->handle(lvParticipant($quiz, $student));

            expect(CarbonImmutable::parse($view['session']['ends_at'])->utc()->toDateTimeString())->toBe('2026-11-02 10:15:00')
                ->and($view['session']['remaining_seconds'])->toBe(900);
        }
    });

    it('queues the finish for the end time', function () {
        Queue::fake();
        $quiz = lvQuiz();
        $session = lvRun($quiz);

        Queue::assertPushed(FinishLiveSessionJob::class, fn (FinishLiveSessionJob $job): bool => $job->sessionId === $session->id
            && $job->delay instanceof DateTimeInterface
            && CarbonImmutable::instance($job->delay)->equalTo($session->ends_at));
    });

    it('starts a scheduled quiz at its time without teacher action (UC-024)', function () {
        $quiz = lvQuiz(scheduledAt: CarbonImmutable::parse('2026-11-02 10:00:30'));

        // Students wait in the lobby the first join opened.
        lvJoin($quiz)->assertSessionHasNoErrors();
        expect(lvSession($quiz)->status)->toBe(LiveSessionStatus::Lobby)
            ->and(lvSession($quiz)->host_id)->toBe($this->teacher->id);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:29'));
        $this->artisan('live:advance')->assertSuccessful();
        expect(lvSession($quiz)->status)->toBe(LiveSessionStatus::Lobby);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:30'));
        $this->artisan('live:advance')->assertSuccessful();

        $session = lvSession($quiz);
        expect($session->status)->toBe(LiveSessionStatus::Running)
            ->and($session->started_at->toDateTimeString())->toBe('2026-11-02 10:00:30')
            ->and($session->ends_at->toDateTimeString())->toBe('2026-11-02 10:15:30')
            ->and($session->participants()->count())->toBe(1);
    });

    it('starts a scheduled quiz nobody joined yet in a new session hosted by the teacher', function () {
        $quiz = lvQuiz(scheduledAt: CarbonImmutable::parse('2026-11-02 10:05:00'));

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:05:00'));
        $this->artisan('live:advance')->assertSuccessful();

        expect(lvSession($quiz)->status)->toBe(LiveSessionStatus::Running)
            ->and(lvSession($quiz)->host_id)->toBe($this->teacher->id);

        // Running it again changes nothing.
        $this->artisan('live:advance')->assertSuccessful();
        expect(LiveSession::query()->count())->toBe(1);
    });

    it('does not start a scheduled quiz that is a draft', function () {
        $quiz = lvQuiz(scheduledAt: CarbonImmutable::parse('2026-11-02 10:05:00'), state: 'draft');

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:06:00'));
        $this->artisan('live:advance')->assertSuccessful();

        expect(LiveSession::query()->where('quiz_id', $quiz->activity_id)->exists())->toBeFalse();
    });

    it('schedules live:advance every five seconds', function () {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'live:advance'));

        expect($events)->toHaveCount(1)
            ->and($events->first()->repeatSeconds)->toBe(5);
    });

    it('refuses the teacher pressing Start on a scheduled quiz', function () {
        $quiz = lvQuiz(scheduledAt: CarbonImmutable::parse('2026-11-02 11:00:00'));
        lvOpen($quiz)->assertSessionHasNoErrors();

        lvStart(lvSession($quiz))->assertSessionHasErrors(['session' => __('live.errors.scheduled_start')]);

        expect(lvSession($quiz)->status)->toBe(LiveSessionStatus::Lobby);
    });

    it('lets only the host teacher start or close a lobby', function () {
        $quiz = lvQuiz();
        lvOpen($quiz)->assertSessionHasNoErrors();
        $session = lvSession($quiz);

        lvOpen($quiz, $this->otherTeacher)->assertForbidden();
        lvStart($session, $this->otherTeacher)->assertForbidden();
        lvStart($session, $this->student)->assertForbidden();
        $this->actingAs($this->otherTeacher)->post(lvUrl("live/sessions/{$session->id}/cancel"))->assertForbidden();

        expect($session->refresh()->status)->toBe(LiveSessionStatus::Lobby);
    });

    it('refuses a lobby for a self-paced quiz', function () {
        $quiz = Quiz::factory()
            ->for(Activity::factory()->for($this->space, 'courseSpace')->published()->create(), 'activity')
            ->withQuestions(1)
            ->create();

        lvOpen($quiz)->assertSessionHasErrors(['session' => __('live.errors.not_live')]);

        expect(LiveSession::query()->exists())->toBeFalse();
    });
});

describe('running it once', function () {
    it('refuses to start a finished live quiz again (UC-024, SCOPE-019)', function () {
        $quiz = lvQuiz();
        $session = lvRun($quiz);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:15:00'));
        $this->artisan('live:advance')->assertSuccessful();
        expect($session->refresh()->status)->toBe(LiveSessionStatus::Finished);

        lvStart($session)->assertSessionHasErrors(['session' => __('live.errors.already_run')]);
        lvOpen($quiz)->assertSessionHasErrors(['session' => __('live.errors.already_run')]);

        expect($session->refresh()->status)->toBe(LiveSessionStatus::Finished)
            ->and(LiveSession::query()->count())->toBe(1);
    });

    it('allows a new lobby after a lobby was closed without starting (UC-024)', function () {
        $quiz = lvQuiz();
        lvOpen($quiz)->assertSessionHasNoErrors();
        $first = lvSession($quiz);

        $this->actingAs($this->teacher)->post(lvUrl("live/sessions/{$first->id}/cancel"))->assertSessionHasNoErrors();
        expect($first->refresh()->status)->toBe(LiveSessionStatus::Cancelled);

        lvOpen($quiz)->assertSessionHasNoErrors();
        $second = lvSession($quiz);

        expect($second->id)->not->toBe($first->id)
            ->and($second->status)->toBe(LiveSessionStatus::Lobby);

        lvJoin($quiz)->assertSessionHasNoErrors();
        lvStart($second)->assertSessionHasNoErrors();
        expect($second->refresh()->status)->toBe(LiveSessionStatus::Running);
    });

    it('returns the open lobby instead of a second one', function () {
        $quiz = lvQuiz();
        lvOpen($quiz)->assertSessionHasNoErrors();
        lvOpen($quiz)->assertSessionHasNoErrors();

        expect(LiveSession::query()->count())->toBe(1);
    });

    it('allows at most one non-cancelled session per quiz in the database (SCOPE-022)', function () {
        $quiz = lvQuiz();
        LiveSession::factory()->for($quiz, 'quiz')->for($this->teacher, 'host')->cancelled()->create();
        LiveSession::factory()->for($quiz, 'quiz')->for($this->teacher, 'host')->create();

        expect(fn () => LiveSession::factory()->for($quiz, 'quiz')->for($this->teacher, 'host')->create())
            ->toThrow(UniqueConstraintViolationException::class);
    });
});

describe('progress', function () {
    it('shows an answer of one of 50 students in the teacher\'s progress view within 1 second (UC-024)', function () {
        $quiz = lvQuiz([Question::factory(), Question::factory()]);
        $students = [$this->student];

        for ($i = 1; $i < 50; $i++) {
            $students[] = lvEnrolledStudent();
        }

        $session = lvRun($quiz, $students);
        [$first] = lvPlacements($quiz);
        $participant = lvParticipant($quiz);

        $before = microtime(true);
        lvAnswer($participant, $first, ['option_ids' => lvOptions($first, ['A'])])->assertOk();
        $progress = app(ShowLiveProgress::class)->handle($session->refresh());
        $elapsed = microtime(true) - $before;

        $rows = collect($progress['participants'])->keyBy('id');

        expect($elapsed)->toBeLessThan(1.0)
            ->and($progress['participants'])->toHaveCount(50)
            ->and($rows[$participant->id]['answered'])->toBe(1)
            ->and($rows[$participant->id]['submitted'])->toBeFalse()
            ->and($rows[$participant->id]['student'])->toBe($this->student->publicProfile())
            ->and($rows->where('answered', 0))->toHaveCount(49)
            ->and($progress['session']['question_count'])->toBe(2)
            ->and(json_encode($progress))->not->toContain('is_correct')
            ->and(json_encode($progress))->not->toContain($this->student->email);
    });
});

describe('finishing', function () {
    it('finishes and scores at the end time when the teacher has gone (UC-024)', function () {
        Event::fake([LiveSessionFinished::class]);
        $quiz = lvQuiz();
        $session = lvRun($quiz);
        [$placement] = lvPlacements($quiz);
        lvAnswer(lvParticipant($quiz), $placement, ['option_ids' => lvOptions($placement, ['A'])])->assertOk();

        // No request of the teacher from here on.
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:14:59'));
        $this->artisan('live:advance')->assertSuccessful();
        expect($session->refresh()->status)->toBe(LiveSessionStatus::Running);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:15:00'));
        $this->artisan('live:advance')->assertSuccessful();

        $participant = lvParticipant($quiz);
        expect($session->refresh()->status)->toBe(LiveSessionStatus::Finished)
            ->and($session->finished_at->toDateTimeString())->toBe('2026-11-02 10:15:00')
            ->and($participant->score)->toBe('1.00')
            ->and($participant->final_rank)->toBe(1)
            ->and($participant->submitted_at->toDateTimeString())->toBe('2026-11-02 10:15:00');

        Event::assertDispatched(LiveSessionFinished::class, fn (LiveSessionFinished $e): bool => $e->sessionId === $session->id
            && $e->quizId === $quiz->activity_id
            && $e->organizationId === $this->acme->id);
    });

    it('finishes from the queued job at the end time', function () {
        $quiz = lvQuiz();
        $session = lvRun($quiz);

        $job = new FinishLiveSessionJob($this->acme->id, $session->id);

        $this->withoutTenant();
        app()->call([$job, 'handle']);
        $this->actingAsTenant($this->acme);
        expect($session->refresh()->status)->toBe(LiveSessionStatus::Running);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:15:00'));
        $this->withoutTenant();
        app()->call([$job, 'handle']);
        $this->actingAsTenant($this->acme);

        expect($session->refresh()->status)->toBe(LiveSessionStatus::Finished);
    });

    it('finishes immediately when the 30th of 30 joined students submits (UC-024)', function () {
        $students = [$this->student, $this->classmate];

        for ($i = 2; $i < 30; $i++) {
            $students[] = lvEnrolledStudent();
        }

        $quiz = lvQuiz();
        $session = lvRun($quiz, $students);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:05:00'));

        foreach (array_slice($students, 0, 29) as $student) {
            lvSubmit(lvParticipant($quiz, $student), $student)->assertSessionHasNoErrors();
        }

        expect($session->refresh()->status)->toBe(LiveSessionStatus::Running);

        lvSubmit(lvParticipant($quiz, $students[29]), $students[29])->assertSessionHasNoErrors();

        expect($session->refresh()->status)->toBe(LiveSessionStatus::Finished)
            ->and($session->finished_at->toDateTimeString())->toBe('2026-11-02 10:05:00')
            ->and(LiveParticipant::query()->whereNull('final_rank')->exists())->toBeFalse();
    });

    it('does not finish while anyone joined has not submitted', function () {
        $quiz = lvQuiz();
        $session = lvRun($quiz, [$this->student, $this->classmate]);

        expect(app(FinishLiveSession::class)->handle($session))->toBeFalse();

        lvSubmit(lvParticipant($quiz))->assertSessionHasNoErrors();

        expect($session->refresh()->status)->toBe(LiveSessionStatus::Running);
    });

    it('ranks by score with equal scores sharing a rank', function () {
        expect(FinishLiveSession::ranks(['a' => 100, 'b' => 300, 'c' => 100, 'd' => 50]))
            ->toBe(['b' => 1, 'a' => 2, 'c' => 2, 'd' => 4]);
    });

    it('awards nothing to My results; LiveSessionFinished is what XP listens to (UC-025)', function () {
        Event::fake([LiveSessionFinished::class]);
        $quiz = lvQuiz();
        lvRun($quiz);
        lvSubmit(lvParticipant($quiz))->assertSessionHasNoErrors();

        expect(lvSession($quiz)->status)->toBe(LiveSessionStatus::Finished)
            ->and(QuizAttempt::query()->exists())->toBeFalse()
            ->and(app(ListMyResults::class)->handle($this->student))->toBe([]);

        Event::assertDispatchedTimes(LiveSessionFinished::class, 1);
    });
});

describe('scoring', function () {
    it('gives 2 of 3 points for 2 of 3 correct options selected (UC-024)', function () {
        $quiz = lvQuiz([Question::factory()->multipleChoice()->withOptions([
            ['A', true], ['B', true], ['C', true], ['D', false],
        ])->state(['points' => '3.00'])]);
        lvRun($quiz);
        [$placement] = lvPlacements($quiz);

        lvAnswer(lvParticipant($quiz), $placement, ['option_ids' => lvOptions($placement, ['A', 'B'])])->assertOk();
        lvSubmit(lvParticipant($quiz))->assertSessionHasNoErrors();

        $answer = LiveAnswer::query()->sole();
        expect($answer->points)->toBe('2.00')
            ->and($answer->is_correct)->toBeFalse()
            ->and(lvParticipant($quiz)->score)->toBe('2.00');
    });

    it('scores matching with partial credit', function () {
        $quiz = lvQuiz([Question::factory()->matching()->state(['points' => '2.00'])]);
        lvRun($quiz);
        [$placement] = lvPlacements($quiz);
        $participant = lvParticipant($quiz);
        [$france, $greece] = $placement->question->pairs->all();

        lvAnswer($participant, $placement, ['matches' => [
            ['left_id' => $france->id, 'right_id' => LiveMatchingItems::rightId($participant, $france->id)],
            ['left_id' => $greece->id, 'right_id' => LiveMatchingItems::rightId($participant, $france->id)],
        ]])->assertOk();
        lvSubmit($participant)->assertSessionHasNoErrors();

        expect(LiveAnswer::query()->sole()->points)->toBe('1.00');
    });

    it('never applies negative marking (SCOPE-022)', function () {
        $quiz = lvQuiz();
        $quiz->forceFill(['negative_marking' => true, 'negative_mark_fraction' => '50'])->save();
        lvRun($quiz);
        [$placement] = lvPlacements($quiz);

        lvAnswer(lvParticipant($quiz), $placement, ['option_ids' => lvOptions($placement, ['B'])])->assertOk();
        lvSubmit(lvParticipant($quiz))->assertSessionHasNoErrors();

        expect(LiveAnswer::query()->sole()->points)->toBe('0.00')
            ->and(lvParticipant($quiz)->score)->toBe('0.00');
    });

    it('scores the last answer given to a question (UC-025)', function () {
        $quiz = lvQuiz([
            Question::factory(),
            Question::factory()->withOptions([['B', false], ['C', true]]),
        ]);
        lvRun($quiz);
        [, $second] = lvPlacements($quiz);
        $participant = lvParticipant($quiz);

        lvAnswer($participant, $second, ['option_ids' => lvOptions($second, ['B'])])->assertOk();
        lvAnswer($participant, $second, ['option_ids' => lvOptions($second, ['C'])])->assertOk();
        lvSubmit($participant)->assertSessionHasNoErrors();

        $answer = LiveAnswer::query()->sole();
        expect($answer->response)->toBe(['option_ids' => lvOptions($second, ['C'])])
            ->and($answer->is_correct)->toBeTrue()
            ->and($answer->points)->toBe('1.00')
            ->and(lvParticipant($quiz)->score)->toBe('1.00');
    });

    it('rejects options of another question', function () {
        $quiz = lvQuiz([Question::factory(), Question::factory()]);
        lvRun($quiz);
        [$first, $second] = lvPlacements($quiz);

        lvAnswer(lvParticipant($quiz), $first, ['option_ids' => lvOptions($second, ['A'])])
            ->assertStatus(422)
            ->assertJsonPath('type', 'urn:uplift:problem:validation');

        expect(LiveAnswer::query()->exists())->toBeFalse();
    });
});

describe('playing', function () {
    it('refuses a join from a student not enrolled in the course space (UC-025, SEC-005)', function () {
        $quiz = lvQuiz();
        lvOpen($quiz)->assertSessionHasNoErrors();

        lvJoin($quiz, $this->outsider)->assertForbidden();
        lvJoin($quiz, $this->otherTeacher)->assertForbidden();

        expect(LiveParticipant::query()->exists())->toBeFalse();
    });

    it('refuses a join before a manual quiz has a lobby', function () {
        $quiz = lvQuiz();

        lvJoin($quiz)->assertSessionHasErrors(['session' => __('live.errors.not_open')]);

        expect(LiveSession::query()->exists())->toBeFalse();
    });

    it('gives a student joining at 10:10 of a quiz ending 10:15 five minutes (UC-025)', function () {
        $quiz = lvQuiz();
        lvRun($quiz, [$this->classmate]);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:10:00'));
        lvJoin($quiz)->assertSessionHasNoErrors();

        $view = app(ShowLiveParticipation::class)->handle(lvParticipant($quiz));

        expect($view['session']['remaining_seconds'])->toBe(300)
            ->and(CarbonImmutable::parse($view['session']['ends_at'])->utc()->toDateTimeString())->toBe('2026-11-02 10:15:00')
            ->and(CarbonImmutable::parse($view['session']['server_now'])->utc()->toDateTimeString())->toBe('2026-11-02 10:10:00');
    });

    it('continues the same participation when the student joins again', function () {
        $quiz = lvQuiz();
        lvRun($quiz);
        [$placement] = lvPlacements($quiz);
        lvAnswer(lvParticipant($quiz), $placement, ['option_ids' => lvOptions($placement, ['A'])])->assertOk();

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:07:00'));
        lvJoin($quiz)->assertSessionHasNoErrors();

        $view = app(ShowLiveParticipation::class)->handle(lvParticipant($quiz));
        expect(LiveParticipant::query()->count())->toBe(1)
            ->and($view['answers'])->toHaveCount(1)
            ->and($view['session']['remaining_seconds'])->toBe(480);
    });

    it('refuses a join after the session finished', function () {
        $quiz = lvQuiz();
        lvRun($quiz, [$this->classmate]);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:15:00'));

        lvJoin($quiz)->assertSessionHasErrors(['session' => __('live.errors.closed')]);

        expect(LiveParticipant::query()->where('student_id', $this->student->id)->exists())->toBeFalse();
    });

    it('refuses an answer before the start', function () {
        $quiz = lvQuiz();
        lvOpen($quiz);
        lvJoin($quiz);
        [$placement] = lvPlacements($quiz);

        lvAnswer(lvParticipant($quiz), $placement, ['option_ids' => lvOptions($placement, ['A'])])
            ->assertStatus(409)
            ->assertJsonPath('type', LiveServiceProvider::PROBLEM_CLOSED);

        expect(LiveAnswer::query()->exists())->toBeFalse();
    });

    it('refuses changing an answer after submitting early (UC-025)', function () {
        $quiz = lvQuiz([Question::factory()->withOptions([['B', false], ['C', true]])]);
        lvRun($quiz, [$this->student, $this->classmate]);
        [$placement] = lvPlacements($quiz);
        $participant = lvParticipant($quiz);

        lvAnswer($participant, $placement, ['option_ids' => lvOptions($placement, ['B'])])->assertOk();
        lvSubmit($participant)->assertSessionHasNoErrors();

        lvAnswer($participant, $placement, ['option_ids' => lvOptions($placement, ['C'])])
            ->assertStatus(409)
            ->assertJsonPath('type', LiveServiceProvider::PROBLEM_CLOSED);
        lvSubmit($participant)->assertSessionHasErrors(['session' => __('live.errors.closed')]);

        expect(LiveAnswer::query()->sole()->response)->toBe(['option_ids' => lvOptions($placement, ['B'])]);
    });

    it('never tells a student whether an answer is correct before the finish (UC-025, SCOPE-021)', function () {
        $quiz = lvQuiz();
        lvRun($quiz, [$this->student, $this->classmate]);
        [$placement] = lvPlacements($quiz);
        $participant = lvParticipant($quiz);

        $response = lvAnswer($participant, $placement, ['option_ids' => lvOptions($placement, ['A'])])->assertOk();
        expect(array_keys($response->json()))->toBe(['saved_at']);

        $during = app(ShowLiveParticipation::class)->handle($participant);
        expect($during['answers'][0])->toBe(['quiz_question_id' => $placement->id, 'response' => ['option_ids' => lvOptions($placement, ['A'])]])
            ->and($during['participant']['score'])->toBeNull()
            ->and(app(ShowLiveResults::class)->handle(lvSession($quiz), $participant))->toBeNull()
            ->and(LiveAnswer::query()->sole()->is_correct)->toBeNull();

        lvSubmit($participant)->assertSessionHasNoErrors();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:15:00'));
        $this->artisan('live:advance')->assertSuccessful();

        $after = app(ShowLiveParticipation::class)->handle($participant->refresh());
        expect($after['answers'][0]['is_correct'])->toBeTrue()
            ->and($after['answers'][0]['points'])->toBe('1.00')
            ->and($after['participant']['score'])->toBe('1.00')
            ->and($after['participant']['final_rank'])->toBe(1);
    });

    it('does not count an answer arriving after the common end time (UC-025)', function () {
        $quiz = lvQuiz([Question::factory()->withOptions([['B', false], ['C', true]])]);
        lvRun($quiz);
        [$placement] = lvPlacements($quiz);
        $participant = lvParticipant($quiz);
        lvAnswer($participant, $placement, ['option_ids' => lvOptions($placement, ['B'])])->assertOk();

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:15:00'));
        lvAnswer($participant, $placement, ['option_ids' => lvOptions($placement, ['C'])])
            ->assertStatus(409)
            ->assertJsonPath('type', LiveServiceProvider::PROBLEM_CLOSED);

        $answer = LiveAnswer::query()->sole();
        expect($answer->response)->toBe(['option_ids' => lvOptions($placement, ['B'])])
            ->and($answer->points)->toBe('0.00')
            ->and(lvSession($quiz)->status)->toBe(LiveSessionStatus::Finished)
            ->and(lvParticipant($quiz)->score)->toBe('0.00');
    });

    it('does not count an answer saved at or after the end time even if one got in', function () {
        $quiz = lvQuiz();
        $session = lvRun($quiz);
        [$placement] = lvPlacements($quiz);
        $participant = lvParticipant($quiz);

        $late = new LiveAnswer;
        $late->live_participant_id = $participant->id;
        $late->quiz_question_id = $placement->id;
        $late->response = ['option_ids' => lvOptions($placement, ['A'])];
        $late->answered_at = CarbonImmutable::parse('2026-11-02 10:15:00');
        $late->save();

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:15:01'));
        expect(app(FinishLiveSession::class)->handle($session))->toBeTrue()
            ->and($late->refresh()->points)->toBe('0.00')
            ->and($participant->refresh()->score)->toBe('0.00');
    });
});

describe('ranking', function () {
    it('shows the ranking and podium by nickname and avatar only when leaderboards are enabled (SEC-008)', function () {
        $quiz = lvQuiz();
        lvRun($quiz, [$this->student, $this->classmate]);
        [$placement] = lvPlacements($quiz);
        lvAnswer(lvParticipant($quiz), $placement, ['option_ids' => lvOptions($placement, ['A'])])->assertOk();
        lvSubmit(lvParticipant($quiz))->assertSessionHasNoErrors();
        lvSubmit(lvParticipant($quiz, $this->classmate), $this->classmate)->assertSessionHasNoErrors();

        $session = lvSession($quiz);
        $student = app(ShowLiveResults::class)->handle($session, lvParticipant($quiz));
        $host = app(ShowLiveResults::class)->handle($session);

        expect($student['ranking'])->toBe([
            ['rank' => 1, 'student' => $this->student->publicProfile()],
            ['rank' => 2, 'student' => $this->classmate->publicProfile()],
        ])
            ->and($student['podium'])->toHaveCount(2)
            ->and($student['own'])->toBe(['score' => '1.00', 'final_rank' => 1])
            ->and($student)->not->toHaveKey('participants')
            ->and(json_encode($student['ranking']))->not->toContain('score')
            ->and($host['ranking'])->toBe($student['ranking'])
            ->and($host['participants'][0]['score'])->toBe('1.00')
            ->and($host['questions'])->toBe([['quiz_question_id' => $placement->id, 'answered' => 1, 'correct' => 1]]);
    });

    it('shows neither teacher nor students a ranking or podium when leaderboards are disabled (UC-024)', function () {
        $this->acme->forceFill(['leaderboard_enabled' => false])->save();
        $quiz = lvQuiz();
        lvRun($quiz, [$this->student, $this->classmate]);
        lvSubmit(lvParticipant($quiz))->assertSessionHasNoErrors();
        lvSubmit(lvParticipant($quiz, $this->classmate), $this->classmate)->assertSessionHasNoErrors();

        $session = lvSession($quiz);
        $student = app(ShowLiveResults::class)->handle($session, lvParticipant($quiz));
        $host = app(ShowLiveResults::class)->handle($session);
        $own = app(ShowLiveParticipation::class)->handle(lvParticipant($quiz));

        expect($student['leaderboard'])->toBeFalse()
            ->and($student['ranking'])->toBeNull()
            ->and($student['podium'])->toBeNull()
            ->and($student['own'])->toBe(['score' => '0.00', 'final_rank' => null])
            ->and($host['ranking'])->toBeNull()
            ->and($host['podium'])->toBeNull()
            ->and(collect($host['participants'])->pluck('final_rank')->unique()->all())->toBe([null])
            ->and($own['participant']['final_rank'])->toBeNull()
            ->and($own['participant']['score'])->toBe('0.00');
    });
});

describe('deleting the quiz (SCOPE-029)', function () {
    it('refuses deleting a live quiz whose session has started', function (string $state) {
        $quiz = lvQuiz();
        lvRun($quiz);

        if ($state === 'finished') {
            lvSubmit(lvParticipant($quiz))->assertSessionHasNoErrors();
        }

        $this->actingAs($this->teacher)
            ->delete(lvUrl("activities/{$quiz->activity_id}"))
            ->assertSessionHasErrors(['activity' => __('activities.errors.has_submissions')]);

        expect(Activity::query()->find($quiz->activity_id))->not->toBeNull();
    })->with(['running', 'finished']);

    it('soft-deletes a live quiz whose session is in the lobby or was cancelled and has no attempts', function (string $state) {
        $quiz = lvQuiz();
        lvOpen($quiz)->assertSessionHasNoErrors();
        lvJoin($quiz)->assertSessionHasNoErrors();

        if ($state === 'cancelled') {
            $this->actingAs($this->teacher)->post(lvUrl('live/sessions/'.lvSession($quiz)->id.'/cancel'))->assertSessionHasNoErrors();
        }

        $this->actingAs($this->teacher)
            ->delete(lvUrl("activities/{$quiz->activity_id}"))
            ->assertSessionHasNoErrors();

        expect(Activity::query()->find($quiz->activity_id))->toBeNull()
            ->and(Activity::query()->withTrashed()->find($quiz->activity_id)?->trashed())->toBeTrue();
    })->with(['lobby', 'cancelled']);

    it('counts a started session as a submission of the quiz', function () {
        $quiz = lvQuiz();
        lvRun($quiz);

        $types = app(ActivityTypes::class);
        $type = $types->for($quiz->activity);

        expect($type)->toBeInstanceOf(LiveQuizType::class)
            ->and($type->hasSubmissions($quiz->activity))->toBeTrue();
    });
});

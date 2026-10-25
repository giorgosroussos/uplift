<?php

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use App\Modules\Live\Enums\LiveSessionStatus;
use App\Modules\Live\Events\LiveProgressUpdated;
use App\Modules\Live\Models\LiveAnswer;
use App\Modules\Live\Models\LiveParticipant;
use App\Modules\Live\Models\LiveSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| P5-T4: the teacher's monitor page of a live quiz (UC-024): open the
| lobby, see who joins, start (manual) or wait for the scheduled start,
| follow live progress (answered counts and submissions only, OWN-013)
| and see the results (ranking and podium only with leaderboards
| enabled). Only the owning teacher reaches it and only the host sees
| the session's state (OWN-012). The client side (broadcasts applied at
| once, countdown from the server clock) is covered by
| resources/js/lib/liveMonitor.test.ts.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->admin = User::factory()->orgAdmin()->create();
    $this->student = User::factory()->student()->create(['nickname' => 'Owl']);

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();
});

function mnUrl(string $path): string
{
    return test()->tenantUrl(test()->acme, ltrim($path, '/'));
}

/**
 * A published live quiz (15 minutes, manual start unless a scheduled
 * start is given) of the course space.
 *
 * @param  list<mixed>  $questions  question factories
 */
function mnQuiz(array $questions = [], ?CarbonImmutable $scheduledAt = null): Quiz
{
    $quiz = Quiz::factory()->for(Activity::factory()->for(test()->space, 'courseSpace')->published()->create(), 'activity');
    $quiz = $scheduledAt === null ? $quiz->live() : $quiz->scheduled($scheduledAt);

    foreach ($questions === [] ? [Question::factory()->withOptions([['B', false], ['C', true]])] : $questions as $question) {
        $quiz = $quiz->withQuestions(1, $question);
    }

    return $quiz->create();
}

function mnMonitor(Quiz $quiz, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->teacher)->get(mnUrl("live/quizzes/{$quiz->activity_id}"));
}

function mnSession(Quiz $quiz): LiveSession
{
    return LiveSession::query()->where('quiz_id', $quiz->activity_id)->where('status', '<>', 'cancelled')->sole();
}

function mnOpen(Quiz $quiz, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->teacher)
        ->from(mnUrl("live/quizzes/{$quiz->activity_id}"))
        ->post(mnUrl("live/quizzes/{$quiz->activity_id}/lobby"));
}

function mnJoin(Quiz $quiz, ?User $student = null): TestResponse
{
    return test()->actingAs($student ?? test()->student)->post(mnUrl("live/quizzes/{$quiz->activity_id}/join"));
}

function mnStart(LiveSession $session, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->teacher)
        ->from(mnUrl("live/quizzes/{$session->quiz_id}"))
        ->post(mnUrl("live/sessions/{$session->id}/start"));
}

function mnCancel(LiveSession $session, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->teacher)
        ->from(mnUrl("live/quizzes/{$session->quiz_id}"))
        ->post(mnUrl("live/sessions/{$session->id}/cancel"));
}

function mnParticipant(Quiz $quiz, ?User $student = null): LiveParticipant
{
    return LiveParticipant::query()
        ->where('live_session_id', mnSession($quiz)->id)
        ->where('student_id', ($student ?? test()->student)->id)
        ->sole();
}

/**
 * @param  list<string>  $options  option texts
 */
function mnAnswer(Quiz $quiz, array $options, ?User $student = null): TestResponse
{
    /** @var QuizQuestion $placement */
    $placement = $quiz->quizQuestions()->with('question.options')->orderBy('position')->firstOrFail();
    $ids = $placement->question->options->whereIn('text', $options)->pluck('id')->values()->all();

    return test()->actingAs($student ?? test()->student)
        ->putJson(mnUrl('live/participants/'.mnParticipant($quiz, $student)->id."/answers/{$placement->id}"), ['option_ids' => $ids]);
}

function mnSubmit(Quiz $quiz, ?User $student = null): TestResponse
{
    return test()->actingAs($student ?? test()->student)->post(mnUrl('live/participants/'.mnParticipant($quiz, $student)->id.'/submit'));
}

function mnEnrolledStudent(): User
{
    $student = User::factory()->student()->create();
    Enrolment::factory()->for(test()->space, 'courseSpace')->for($student, 'student')->create();

    return $student;
}

/**
 * Opens the lobby, lets the students join and starts now.
 *
 * @param  list<User>  $students
 */
function mnRun(Quiz $quiz, array $students = []): LiveSession
{
    mnOpen($quiz)->assertSessionHasNoErrors();

    foreach ($students === [] ? [test()->student] : $students as $student) {
        mnJoin($quiz, $student)->assertSessionHasNoErrors();
    }

    mnStart(mnSession($quiz))->assertSessionHasNoErrors();

    return mnSession($quiz)->refresh();
}

describe('the monitor page', function () {
    it('shows the quiz, its questions in order and no session before a lobby is opened', function () {
        $quiz = mnQuiz([
            Question::factory()->withOptions([['B', false], ['C', true]])->state(['prompt' => 'First?']),
            Question::factory()->withOptions([['B', false], ['C', true]])->state(['prompt' => 'Second?']),
        ]);

        mnMonitor($quiz)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Live/Monitor')
            ->where('activity.id', $quiz->activity_id)
            ->where('quiz.start_mode', 'manual')
            ->where('quiz.duration_seconds', 900)
            ->where('questions.0.prompt', 'First?')
            ->where('questions.1.prompt', 'Second?')
            ->where('session', null)
            ->where('state', null));
    });

    it('shows the lobby with the enrolled students as they join, nickname and avatar only (SEC-008)', function () {
        $quiz = mnQuiz();
        mnOpen($quiz)->assertSessionHasNoErrors()->assertRedirect(mnUrl("live/quizzes/{$quiz->activity_id}"));
        mnJoin($quiz)->assertSessionHasNoErrors();

        mnMonitor($quiz)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('session.status', 'lobby')
            ->where('session.hosting', true)
            ->where('state.progress.session.status', 'lobby')
            ->has('state.progress.participants', 1)
            ->where('state.progress.participants.0.student', [
                'id' => $this->student->id,
                'nickname' => 'Owl',
                'avatar' => $this->student->avatar,
            ])
            ->where('state.channels.host', "organization.{$this->acme->id}.live-session.".mnSession($quiz)->id.'.host'));
    });

    it('is not found for a self-paced quiz', function () {
        $quiz = Quiz::factory()->for(Activity::factory()->for($this->space, 'courseSpace')->published()->create(), 'activity')->create();

        mnMonitor($quiz)->assertNotFound();
    });

    it('is not found for an activity of another organisation', function () {
        $quiz = mnQuiz();
        $other = $this->createOrganization(['subdomain' => 'other']);

        $this->actingAs($this->teacher)
            ->get($this->tenantUrl($other, "live/quizzes/{$quiz->activity_id}"))
            ->assertNotFound();
    });
});

describe('UC-024 acceptance', function () {
    it('started at 10:00:00 with 15 minutes ends at 10:15:00 for every student', function () {
        $classmate = mnEnrolledStudent();
        $quiz = mnQuiz();
        $session = mnRun($quiz, [$this->student, $classmate]);

        mnMonitor($quiz)->assertInertia(fn (Assert $page) => $page
            ->where('state.progress.session.status', 'running')
            ->where('state.progress.session.started_at', '2026-11-02T10:00:00+00:00')
            ->where('state.progress.session.ends_at', '2026-11-02T10:15:00+00:00'));

        foreach ([$this->student, $classmate] as $student) {
            $this->actingAs($student)
                ->getJson(mnUrl('live/participants/'.mnParticipant($quiz, $student)->id.'/state'))
                ->assertOk()
                ->assertJsonPath('participation.session.ends_at', $session->ends_at?->toIso8601String());
        }
    });

    it('starts a scheduled quiz at 10:00 without teacher action', function () {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:50:00'));
        $quiz = mnQuiz([], CarbonImmutable::parse('2026-11-02 10:00:00'));
        mnJoin($quiz)->assertSessionHasNoErrors();

        mnMonitor($quiz)->assertInertia(fn (Assert $page) => $page
            ->where('quiz.start_mode', 'scheduled')
            ->where('quiz.scheduled_start_at', '2026-11-02T10:00:00+00:00')
            ->where('state.progress.session.status', 'lobby'));

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));
        $this->artisan('live:advance')->assertSuccessful();

        mnMonitor($quiz)->assertInertia(fn (Assert $page) => $page
            ->where('state.progress.session.status', 'running')
            ->where('state.progress.session.ends_at', '2026-11-02T10:15:00+00:00'));
    });

    it('with 50 joined students reflects an answer in the teacher\'s progress view within 1 second', function () {
        Event::fake([LiveProgressUpdated::class]);
        $students = [$this->student];

        for ($i = 1; $i < 50; $i++) {
            $students[] = mnEnrolledStudent();
        }

        $quiz = mnQuiz();
        $session = mnRun($quiz, $students);

        mnMonitor($quiz)->assertInertia(fn (Assert $page) => $page->has('state.progress.participants', 50));

        $answeredAt = microtime(true);
        mnAnswer($quiz, ['C'])->assertOk();

        // Pushed to the host at once (applied by the monitor without a request) ...
        Event::assertDispatched(LiveProgressUpdated::class, fn (LiveProgressUpdated $event): bool => $event->sessionId === $session->id
            && $event->participant['id'] === mnParticipant($quiz)->id
            && $event->participant['answered'] === 1);

        // ... and in the very next snapshot (reconnection, polling fallback).
        $row = collect($this->actingAs($this->teacher)->getJson(mnUrl("live/sessions/{$session->id}/state"))
            ->assertOk()
            ->json('progress.participants'))
            ->firstWhere('id', mnParticipant($quiz)->id);

        expect($row['answered'])->toBe(1)
            ->and(microtime(true) - $answeredAt)->toBeLessThan(1.0);
    });

    it('finishes and scores at the end time when the teacher has closed the browser', function () {
        $quiz = mnQuiz();
        mnRun($quiz);
        mnAnswer($quiz, ['C'])->assertOk();

        // No request from the teacher from here on.
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:15:00'));
        $this->artisan('live:advance')->assertSuccessful();

        mnMonitor($quiz)->assertInertia(fn (Assert $page) => $page
            ->where('state.progress.session.status', 'finished')
            ->where('state.results.participants.0.score', '1.00'));
    });

    it('refuses to start a finished quiz again', function () {
        $quiz = mnQuiz();
        $session = mnRun($quiz);
        mnSubmit($quiz)->assertSessionHasNoErrors();
        expect($session->refresh()->status)->toBe(LiveSessionStatus::Finished);

        mnOpen($quiz)->assertSessionHasErrors(['session' => __('live.errors.already_run')]);
        mnStart($session)->assertSessionHasErrors(['session' => __('live.errors.already_run')]);

        expect(LiveSession::query()->count())->toBe(1)
            ->and($session->refresh()->status)->toBe(LiveSessionStatus::Finished);
        mnMonitor($quiz)->assertInertia(fn (Assert $page) => $page->where('session.status', 'finished'));
    });

    it('finishes at once when the 30th of 30 joined students submits before the end time', function () {
        $students = [$this->student];

        for ($i = 1; $i < 30; $i++) {
            $students[] = mnEnrolledStudent();
        }

        $quiz = mnQuiz();
        $session = mnRun($quiz, $students);

        foreach (array_slice($students, 0, 29) as $student) {
            mnSubmit($quiz, $student)->assertSessionHasNoErrors();
        }

        mnMonitor($quiz)->assertInertia(fn (Assert $page) => $page
            ->where('state.progress.session.status', 'running')
            ->where('state.progress.participants', fn ($rows): bool => collect($rows)->where('submitted', true)->count() === 29));

        mnSubmit($quiz, $students[29])->assertSessionHasNoErrors();

        expect($session->refresh()->status)->toBe(LiveSessionStatus::Finished)
            ->and($session->finished_at?->toIso8601String())->toBe('2026-11-02T10:00:00+00:00');
        mnMonitor($quiz)->assertInertia(fn (Assert $page) => $page
            ->where('state.progress.session.status', 'finished')
            ->has('state.results.participants', 30));
    });

    it('allows a new lobby after the lobby was closed without starting', function () {
        $quiz = mnQuiz();
        mnOpen($quiz)->assertSessionHasNoErrors();
        $first = mnSession($quiz);

        mnCancel($first)->assertSessionHasNoErrors();
        mnMonitor($quiz)->assertInertia(fn (Assert $page) => $page->where('session', null));

        mnOpen($quiz)->assertSessionHasNoErrors();
        $second = mnSession($quiz);

        expect($second->id)->not->toBe($first->id)
            ->and($first->refresh()->status)->toBe(LiveSessionStatus::Cancelled);
        mnMonitor($quiz)->assertInertia(fn (Assert $page) => $page
            ->where('session.id', $second->id)
            ->where('session.status', 'lobby'));
    });

    it('shows 2 points for 2 of 3 correct options of a 3-point multiple-choice question', function () {
        $quiz = mnQuiz([Question::factory()->multipleChoice()->withOptions([
            ['A', true], ['B', true], ['C', true], ['D', false],
        ])->state(['points' => '3.00'])]);
        mnRun($quiz);

        mnAnswer($quiz, ['A', 'B'])->assertOk();
        mnSubmit($quiz)->assertSessionHasNoErrors();

        mnMonitor($quiz)->assertInertia(fn (Assert $page) => $page
            ->where('state.results.participants.0.score', '2.00')
            ->where('state.results.questions.0.answered', 1)
            ->where('state.results.questions.0.correct', 0));
    });

    it('shows the host a ranking and podium with leaderboards enabled', function () {
        $quiz = mnQuiz();
        mnRun($quiz);
        mnSubmit($quiz)->assertSessionHasNoErrors();

        mnMonitor($quiz)->assertInertia(fn (Assert $page) => $page
            ->where('state.results.leaderboard', true)
            ->where('state.results.podium.0.rank', 1)
            ->where('state.results.podium.0.student.nickname', 'Owl'));
    });

    it('shows neither the teacher nor students a ranking or podium when leaderboards are disabled', function () {
        $this->acme->forceFill(['leaderboard_enabled' => false])->save();
        $quiz = mnQuiz();
        mnRun($quiz);
        mnAnswer($quiz, ['C'])->assertOk();
        mnSubmit($quiz)->assertSessionHasNoErrors();

        mnMonitor($quiz)->assertInertia(fn (Assert $page) => $page
            ->where('state.results.leaderboard', false)
            ->where('state.results.ranking', null)
            ->where('state.results.podium', null)
            ->where('state.results.participants.0.final_rank', null)
            ->where('state.results.participants.0.score', '1.00'));

        $this->actingAs($this->student)
            ->getJson(mnUrl('live/participants/'.mnParticipant($quiz)->id.'/state'))
            ->assertOk()
            ->assertJsonPath('results.ranking', null)
            ->assertJsonPath('results.podium', null)
            ->assertJsonPath('results.own.final_rank', null)
            ->assertJsonPath('results.own.score', '1.00');
    });
});

describe('OWN-012 and OWN-013 on the monitor', function () {
    it('denies a student the monitor and the host actions; nothing changes', function () {
        $quiz = mnQuiz();
        mnOpen($quiz)->assertSessionHasNoErrors();
        mnJoin($quiz)->assertSessionHasNoErrors();
        $session = mnSession($quiz);

        mnMonitor($quiz, $this->student)->assertForbidden();
        mnStart($session, $this->student)->assertForbidden();
        mnCancel($session, $this->student)->assertForbidden();
        $this->actingAs($this->student)->getJson(mnUrl("live/sessions/{$session->id}/state"))->assertForbidden();

        expect($session->refresh()->status)->toBe(LiveSessionStatus::Lobby)
            ->and(LiveParticipant::query()->count())->toBe(1);
    });

    it('denies a teacher who does not own the course space the monitor and the session; nothing changes', function () {
        $quiz = mnQuiz();
        $session = mnRun($quiz);
        mnAnswer($quiz, ['C'])->assertOk();

        mnMonitor($quiz, $this->otherTeacher)->assertForbidden();
        mnOpen($quiz, $this->otherTeacher)->assertForbidden();
        $this->actingAs($this->otherTeacher)->getJson(mnUrl("live/sessions/{$session->id}/state"))->assertForbidden();
        $this->actingAs($this->otherTeacher)
            ->getJson(mnUrl('live/participants/'.mnParticipant($quiz)->id.'/state'))
            ->assertForbidden();

        expect($session->refresh()->status)->toBe(LiveSessionStatus::Running)
            ->and(LiveAnswer::query()->count())->toBe(1);
    });

    it('denies an org admin the monitor', function () {
        $quiz = mnQuiz();

        mnMonitor($quiz, $this->admin)->assertForbidden();
    });

    it('shows a co-managing teacher who is not the host no session state', function () {
        $quiz = mnQuiz();
        $session = mnRun($quiz);
        // The space changes hands while the session runs: the new owner may
        // manage the quiz but is not the session's host.
        $this->space->forceFill(['teacher_id' => $this->otherTeacher->id])->save();

        mnMonitor($quiz, $this->otherTeacher)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('session.id', $session->id)
            ->where('session.hosting', false)
            ->where('state', null));
    });

    it('never shows the host an answer, its correctness or a score while the session runs (OWN-013)', function () {
        $quiz = mnQuiz();
        mnRun($quiz);
        mnAnswer($quiz, ['C'])->assertOk();

        $props = mnMonitor($quiz)->assertOk()->viewData('page')['props'];
        $json = json_encode($props['state'], JSON_THROW_ON_ERROR);

        expect($props['state']['progress']['participants'][0]['answered'])->toBe(1)
            ->and($props['state']['results'])->toBeNull()
            ->and($json)->not->toContain('"response"')
            ->and($json)->not->toContain('"is_correct"')
            ->and($json)->not->toContain('"score"')
            ->and($json)->not->toContain('"points"')
            ->and($json)->not->toContain('option_ids');
    });
});

describe('ENT-LiveSession state model from the monitor', function () {
    it('rejects closing a running lobby and leaves it running', function () {
        $quiz = mnQuiz();
        $session = mnRun($quiz);

        mnCancel($session)->assertSessionHasErrors(['session' => __('live.errors.already_run')]);

        expect($session->refresh()->status)->toBe(LiveSessionStatus::Running);
    });

    it('rejects starting a closed lobby and leaves it closed', function () {
        $quiz = mnQuiz();
        mnOpen($quiz)->assertSessionHasNoErrors();
        $session = mnSession($quiz);
        mnCancel($session)->assertSessionHasNoErrors();

        mnStart($session)->assertSessionHasErrors(['session' => __('live.errors.not_open')]);

        expect($session->refresh()->status)->toBe(LiveSessionStatus::Cancelled);
    });

    it('rejects starting a scheduled quiz by hand and leaves the lobby waiting', function () {
        $quiz = mnQuiz([], CarbonImmutable::parse('2026-11-02 11:00:00'));
        mnOpen($quiz)->assertSessionHasNoErrors();
        $session = mnSession($quiz);

        mnStart($session)->assertSessionHasErrors(['session' => __('live.errors.scheduled_start')]);

        expect($session->refresh()->status)->toBe(LiveSessionStatus::Lobby);
    });
});

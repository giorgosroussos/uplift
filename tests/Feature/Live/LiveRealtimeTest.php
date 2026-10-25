<?php

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Actions\ListMyResults;
use App\Modules\Assessment\Database\Factories\QuestionFactory;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use App\Modules\Live\Broadcasting\LiveChannels;
use App\Modules\Live\Enums\LiveSessionStatus;
use App\Modules\Live\Events\LiveProgressUpdated;
use App\Modules\Live\Events\LiveSessionChanged;
use App\Modules\Live\Events\LiveSessionFinished;
use App\Modules\Live\Models\LiveAnswer;
use App\Modules\Live\Models\LiveParticipant;
use App\Modules\Live\Models\LiveSession;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
| P5-T3: real-time channels and reconnection (UC-024, UC-025, FLOW-001,
| FLOW-002). Presence and private channels authorised by the Policies with
| the organisation id in the name (SEC-010); progress and status changes
| broadcast with nickname and avatar only and never an answer, score or
| correctness; JSON state snapshots behind the 2-second polling fallback
| (ARCH-006).
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
    $this->student = User::factory()->student()->create();
    $this->classmate = User::factory()->student()->create();
    $this->outsider = User::factory()->student()->create();

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->classmate, 'student')->create();
});

function rtUrl(string $path): string
{
    return test()->tenantUrl(test()->acme, ltrim($path, '/'));
}

/**
 * A published live quiz (15 minutes, manual start unless a scheduled
 * start is given) of the course space.
 *
 * @param  list<QuestionFactory>  $questions
 */
function rtQuiz(array $questions = [], ?CarbonImmutable $scheduledAt = null): Quiz
{
    $quiz = Quiz::factory()->for(Activity::factory()->for(test()->space, 'courseSpace')->published()->create(), 'activity');
    $quiz = $scheduledAt === null ? $quiz->live() : $quiz->scheduled($scheduledAt);

    foreach ($questions === [] ? [Question::factory()] : $questions as $question) {
        $quiz = $quiz->withQuestions(1, $question);
    }

    return $quiz->create();
}

function rtSession(Quiz $quiz): LiveSession
{
    return LiveSession::query()->where('quiz_id', $quiz->activity_id)->where('status', '<>', 'cancelled')->sole();
}

function rtParticipant(Quiz $quiz, ?User $student = null): LiveParticipant
{
    return LiveParticipant::query()
        ->where('live_session_id', rtSession($quiz)->id)
        ->where('student_id', ($student ?? test()->student)->id)
        ->sole();
}

function rtOpen(Quiz $quiz): TestResponse
{
    return test()->actingAs(test()->teacher)->post(rtUrl("live/quizzes/{$quiz->activity_id}/lobby"));
}

function rtJoin(Quiz $quiz, ?User $student = null): TestResponse
{
    return test()->actingAs($student ?? test()->student)->post(rtUrl("live/quizzes/{$quiz->activity_id}/join"));
}

function rtStart(LiveSession $session, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->teacher)->post(rtUrl("live/sessions/{$session->id}/start"));
}

function rtCancel(LiveSession $session): TestResponse
{
    return test()->actingAs(test()->teacher)->post(rtUrl("live/sessions/{$session->id}/cancel"));
}

/**
 * @param  list<string>  $options
 */
function rtAnswer(LiveParticipant $participant, QuizQuestion $placement, array $options, ?User $as = null): TestResponse
{
    $ids = $placement->question->options->whereIn('text', $options)->pluck('id')->values()->all();

    return test()->actingAs($as ?? test()->student)
        ->putJson(rtUrl("live/participants/{$participant->id}/answers/{$placement->id}"), ['option_ids' => $ids]);
}

function rtSubmit(LiveParticipant $participant, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->student)->post(rtUrl("live/participants/{$participant->id}/submit"));
}

/**
 * Opens the lobby, lets the students join and starts the quiz now.
 *
 * @param  list<User>  $students
 */
function rtRun(Quiz $quiz, array $students = []): LiveSession
{
    rtOpen($quiz)->assertSessionHasNoErrors();

    foreach ($students === [] ? [test()->student] : $students as $student) {
        rtJoin($quiz, $student)->assertSessionHasNoErrors();
    }

    rtStart(rtSession($quiz))->assertSessionHasNoErrors();

    return rtSession($quiz)->refresh();
}

/**
 * @return list<QuizQuestion>
 */
function rtPlacements(Quiz $quiz): array
{
    return $quiz->quizQuestions()->with('question.options')->get()->all();
}

function rtEnrolledStudent(): User
{
    $student = User::factory()->student()->create();
    Enrolment::factory()->for(test()->space, 'courseSpace')->for($student, 'student')->create();

    return $student;
}

function rtHostState(LiveSession $session, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->teacher)->getJson(rtUrl("live/sessions/{$session->id}/state"));
}

function rtOwnState(LiveParticipant $participant, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->student)->getJson(rtUrl("live/participants/{$participant->id}/state"));
}

/**
 * Lets /broadcasting/auth sign channel subscriptions as Reverb does
 * (test credentials, nothing is sent), with the Live channels registered
 * on that broadcaster.
 */
function rtSignChannels(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
        'broadcasting.connections.reverb.options.host' => 'localhost',
        'broadcasting.connections.reverb.options.port' => 8080,
        'broadcasting.connections.reverb.options.scheme' => 'http',
    ]);
    Broadcast::forgetDrivers();

    require base_path('app/Modules/Live/channels.php');
}

function rtSubscribe(User $user, string $channel): TestResponse
{
    return test()->actingAs($user)->post(rtUrl('broadcasting/auth'), [
        'socket_id' => '1234.5678',
        'channel_name' => $channel,
    ]);
}

/**
 * Every value the broadcast event sends, as JSON.
 */
function rtPayload(ShouldBroadcast $event): string
{
    return (string) json_encode([
        'channels' => array_map(fn ($c): string => $c->name, $event->broadcastOn()),
        'data' => method_exists($event, 'broadcastWith') ? $event->broadcastWith() : [],
    ]);
}

describe('channels (SEC-010)', function () {
    beforeEach(function () {
        Event::fake([LiveSessionChanged::class, LiveProgressUpdated::class]);

        $this->quiz = rtQuiz();
        $this->session = rtRun($this->quiz, [$this->student]);
        $this->sessionChannel = 'private-'.LiveChannels::session($this->acme->id, $this->session->id);
        $this->hostChannel = 'presence-'.LiveChannels::host($this->acme->id, $this->session->id);

        rtSignChannels();
    });

    it('names every live channel with the organisation id', function () {
        expect(LiveChannels::session($this->acme->id, $this->session->id))
            ->toBe("organization.{$this->acme->id}.live-session.{$this->session->id}")
            ->and(LiveChannels::host($this->acme->id, $this->session->id))
            ->toBe("organization.{$this->acme->id}.live-session.{$this->session->id}.host");
    });

    it('lets the host and joined students into the private session channel, which has no member list', function () {
        foreach ([$this->teacher, $this->student] as $user) {
            $response = rtSubscribe($user, $this->sessionChannel)->assertOk()->assertJsonStructure(['auth']);

            expect($response->json())->not->toHaveKey('channel_data');
        }
    });

    it('refuses the session channel as a presence channel, so students never see who else joined (OWN-012)', function () {
        rtJoin($this->quiz, $this->classmate)->assertSessionHasNoErrors();
        $presence = 'presence-'.LiveChannels::session($this->acme->id, $this->session->id);

        foreach ([$this->student, $this->classmate, $this->teacher] as $user) {
            rtSubscribe($user, $presence)->assertForbidden();
        }
    });

    it('keeps everyone else out of the session channel', function () {
        // Enrolled but not joined, not enrolled, another teacher, the org admin.
        foreach ([$this->classmate, $this->outsider, $this->otherTeacher, $this->admin] as $user) {
            rtSubscribe($user, $this->sessionChannel)->assertForbidden();
        }
    });

    it('lets only the host into the host presence channel, known by nickname and avatar only', function () {
        $response = rtSubscribe($this->teacher, $this->hostChannel)->assertOk();
        $member = json_decode((string) $response->json('channel_data'), true);

        expect($member['user_id'])->toBe($this->teacher->id)
            ->and($member['user_info'])->toBe($this->teacher->publicProfile())
            ->and(array_keys($member['user_info']))->toBe(['id', 'nickname', 'avatar'])
            ->and($response->content())->not->toContain($this->teacher->email);

        // No student is ever a member, joined or not.
        foreach ([$this->student, $this->classmate, $this->outsider, $this->otherTeacher, $this->admin] as $user) {
            rtSubscribe($user, $this->hostChannel)->assertForbidden();
        }
    });

    it('refuses a channel naming another organisation or no session', function () {
        $globex = $this->createOrganization(['subdomain' => 'globex']);

        // The host's own session under another organisation's id.
        rtSubscribe($this->teacher, 'private-'.LiveChannels::session($globex->id, $this->session->id))->assertForbidden();
        rtSubscribe($this->teacher, 'presence-'.LiveChannels::host($globex->id, $this->session->id))->assertForbidden();

        // A session of another organisation under its own id.
        $foreign = $this->asTenant($globex, fn () => LiveSession::factory()->running()->create());
        rtSubscribe($this->teacher, 'private-'.LiveChannels::session($globex->id, $foreign->id))->assertForbidden();
        rtSubscribe($this->teacher, 'presence-'.LiveChannels::host($globex->id, $foreign->id))->assertForbidden();

        // Malformed and unknown sessions.
        rtSubscribe($this->teacher, 'private-'.LiveChannels::session($this->acme->id, 'not-a-uuid'))->assertForbidden();
        rtSubscribe($this->teacher, 'presence-'.LiveChannels::host($this->acme->id, (string) Str::uuid()))->assertForbidden();
    });

    it('refuses guests', function () {
        auth()->logout();

        foreach ([$this->sessionChannel, $this->hostChannel] as $channel) {
            $this->post(rtUrl('broadcasting/auth'), ['socket_id' => '1234.5678', 'channel_name' => $channel])
                ->assertForbidden();
        }
    });

    it('broadcasts status to the private session channel and progress to the host presence channel only', function () {
        $events = [
            LiveSessionChanged::of($this->session),
            LiveProgressUpdated::of(rtParticipant($this->quiz), 0),
        ];

        foreach ($events as $event) {
            foreach ($event->broadcastOn() as $channel) {
                expect($channel instanceof PrivateChannel || $channel instanceof PresenceChannel)->toBeTrue()
                    ->and($channel->name)->toContain("organization.{$this->acme->id}.");
            }
        }

        expect($events[0]->broadcastOn())->toHaveCount(1)
            ->and($events[0]->broadcastOn()[0])->not->toBeInstanceOf(PresenceChannel::class)
            ->and($events[0]->broadcastOn()[0]->name)->toBe($this->sessionChannel)
            ->and($events[1]->broadcastOn())->toHaveCount(1)
            ->and($events[1]->broadcastOn()[0])->toBeInstanceOf(PresenceChannel::class)
            ->and($events[1]->broadcastOn()[0]->name)->toBe($this->hostChannel);
    });
});

describe('broadcasts', function () {
    beforeEach(function () {
        Event::fake([LiveSessionChanged::class, LiveProgressUpdated::class]);
    });

    it('broadcasts the start with one common end time for every student (UC-024)', function () {
        $quiz = rtQuiz();
        $session = rtRun($quiz, [$this->student, $this->classmate]);

        Event::assertDispatched(LiveSessionChanged::class, function (LiveSessionChanged $e) use ($session): bool {
            $data = $e->broadcastWith()['session'];

            return $e->sessionId === $session->id
                && $data['status'] === 'running'
                && CarbonImmutable::parse((string) $data['started_at'])->utc()->toDateTimeString() === '2026-11-02 10:00:00'
                && CarbonImmutable::parse((string) $data['ends_at'])->utc()->toDateTimeString() === '2026-11-02 10:15:00'
                && CarbonImmutable::parse($data['server_now'])->utc()->toDateTimeString() === '2026-11-02 10:00:00'
                && $e->broadcastOn()[0]->name === 'private-'.LiveChannels::session(test()->acme->id, $session->id);
        });

        foreach ([$this->student, $this->classmate] as $student) {
            $state = rtOwnState(rtParticipant($quiz, $student), $student)->assertOk()->json('participation.session');

            expect(CarbonImmutable::parse($state['ends_at'])->utc()->toDateTimeString())->toBe('2026-11-02 10:15:00')
                ->and($state['remaining_seconds'])->toBe(900);
        }
    });

    it('broadcasts a scheduled start at its time without teacher action (UC-024)', function () {
        $quiz = rtQuiz(scheduledAt: CarbonImmutable::parse('2026-11-02 10:00:30'));
        rtJoin($quiz)->assertSessionHasNoErrors();

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:29'));
        $this->artisan('live:advance')->assertSuccessful();
        Event::assertNotDispatched(LiveSessionChanged::class);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:30'));
        $this->artisan('live:advance')->assertSuccessful();

        Event::assertDispatched(LiveSessionChanged::class, fn (LiveSessionChanged $e): bool => $e->sessionId === rtSession($quiz)->id
            && $e->status === 'running'
            && CarbonImmutable::parse((string) $e->endsAt)->utc()->toDateTimeString() === '2026-11-02 10:15:30');

        expect(rtOwnState(rtParticipant($quiz))->json('participation.session.status'))->toBe('running');
    });

    it('sends the host the answered count of one of 50 students at once, never the answer (UC-024)', function () {
        $quiz = rtQuiz([Question::factory(), Question::factory()]);
        $students = [$this->student];

        for ($i = 1; $i < 50; $i++) {
            $students[] = rtEnrolledStudent();
        }

        $session = rtRun($quiz, $students);
        [$first] = rtPlacements($quiz);
        $participant = rtParticipant($quiz);

        $before = microtime(true);
        rtAnswer($participant, $first, ['A'])->assertOk();
        $state = rtHostState($session)->assertOk()->json('progress');
        $elapsed = microtime(true) - $before;

        Event::assertDispatched(LiveProgressUpdated::class, function (LiveProgressUpdated $e) use ($participant): bool {
            $payload = rtPayload($e);

            return $e->participant['id'] === $participant->id
                && $e->participant['answered'] === 1
                && $e->participant['submitted'] === false
                && $e->participant['student'] === test()->student->publicProfile()
                && $e->broadcastOn()[0]->name === 'presence-'.LiveChannels::host(test()->acme->id, $participant->live_session_id)
                && ! str_contains($payload, 'option')
                && ! str_contains($payload, 'is_correct')
                && ! str_contains($payload, 'score')
                && ! str_contains($payload, test()->student->email);
        });

        $rows = collect($state['participants'])->keyBy('id');
        expect($elapsed)->toBeLessThan(1.0)
            ->and($state['participants'])->toHaveCount(50)
            ->and($rows[$participant->id]['answered'])->toBe(1)
            ->and($rows->where('answered', 0))->toHaveCount(49);
    });

    it('sends the host each join and submit as a progress row', function () {
        $quiz = rtQuiz();
        rtRun($quiz, [$this->student, $this->classmate]);

        Event::assertDispatchedTimes(LiveProgressUpdated::class, 2);
        Event::assertDispatched(LiveProgressUpdated::class, fn (LiveProgressUpdated $e): bool => $e->participant['student']['id'] === test()->classmate->id
            && $e->participant['answered'] === 0);

        rtSubmit(rtParticipant($quiz))->assertSessionHasNoErrors();

        Event::assertDispatched(LiveProgressUpdated::class, fn (LiveProgressUpdated $e): bool => $e->participant['student']['id'] === test()->student->id
            && $e->participant['submitted'] === true);
    });

    it('broadcasts the finish at the end time after the teacher has gone, with the results in the snapshots (UC-024)', function () {
        $quiz = rtQuiz();
        $session = rtRun($quiz);
        [$placement] = rtPlacements($quiz);
        rtAnswer(rtParticipant($quiz), $placement, ['A'])->assertOk();

        // The teacher closed their browser: no request of theirs from here on.
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:15:00'));
        $this->artisan('live:advance')->assertSuccessful();

        Event::assertDispatched(LiveSessionChanged::class, fn (LiveSessionChanged $e): bool => $e->sessionId === $session->id
            && $e->status === 'finished'
            && ! str_contains(rtPayload($e), 'score'));

        $own = rtOwnState(rtParticipant($quiz))->assertOk();
        expect($own->json('participation.session.status'))->toBe('finished')
            ->and($own->json('participation.participant.score'))->toBe('1.00')
            ->and($own->json('results.own.score'))->toBe('1.00');

        expect(rtHostState($session)->assertOk()->json('results.participants.0.score'))->toBe('1.00');
    });

    it('broadcasts the finish as soon as the 30th of 30 students submits (UC-024)', function () {
        $students = [$this->student, $this->classmate];

        for ($i = 2; $i < 30; $i++) {
            $students[] = rtEnrolledStudent();
        }

        $quiz = rtQuiz();
        $session = rtRun($quiz, $students);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:05:00'));

        foreach (array_slice($students, 0, 29) as $student) {
            rtSubmit(rtParticipant($quiz, $student), $student)->assertSessionHasNoErrors();
        }

        Event::assertNotDispatched(LiveSessionChanged::class, fn (LiveSessionChanged $e): bool => $e->status === 'finished');

        rtSubmit(rtParticipant($quiz, $students[29]), $students[29])->assertSessionHasNoErrors();

        Event::assertDispatched(LiveSessionChanged::class, fn (LiveSessionChanged $e): bool => $e->sessionId === $session->id && $e->status === 'finished');
        expect(rtHostState($session)->json('progress.session.status'))->toBe('finished');
    });

    it('broadcasts a lobby closed without starting and allows a new lobby (UC-024)', function () {
        $quiz = rtQuiz();
        rtOpen($quiz)->assertSessionHasNoErrors();
        $first = rtSession($quiz);

        rtCancel($first)->assertSessionHasNoErrors();
        Event::assertDispatched(LiveSessionChanged::class, fn (LiveSessionChanged $e): bool => $e->sessionId === $first->id && $e->status === 'cancelled');

        rtOpen($quiz)->assertSessionHasNoErrors();
        $second = rtSession($quiz);
        expect($second->id)->not->toBe($first->id)
            ->and($second->status)->toBe(LiveSessionStatus::Lobby);
        rtHostState($second)->assertOk()->assertJsonPath('progress.session.status', 'lobby');
    });

    it('broadcasts nothing when a finished quiz is started again (UC-024)', function () {
        $quiz = rtQuiz();
        $session = rtRun($quiz);
        rtSubmit(rtParticipant($quiz))->assertSessionHasNoErrors();
        Event::fake([LiveSessionChanged::class, LiveProgressUpdated::class]);

        rtStart($session)->assertSessionHasErrors('session');

        expect($session->refresh()->status)->toBe(LiveSessionStatus::Finished);
        Event::assertNotDispatched(LiveSessionChanged::class);
    });

    it('queues broadcasts after commit with plain values only, so no tenant or model is needed to send them', function () {
        $quiz = rtQuiz();
        rtRun($quiz);

        foreach ([LiveSessionChanged::class, LiveProgressUpdated::class] as $class) {
            expect(is_subclass_of($class, ShouldBroadcast::class))->toBeTrue()
                ->and(is_subclass_of($class, ShouldDispatchAfterCommit::class))->toBeTrue();
        }

        Event::assertDispatched(LiveSessionChanged::class, fn (LiveSessionChanged $e): bool => ! str_contains(serialize($e), 'Illuminate\\Database'));
        Event::assertDispatched(LiveProgressUpdated::class, fn (LiveProgressUpdated $e): bool => ! str_contains(serialize($e), 'Illuminate\\Database'));
    });
});

describe('state snapshots (FLOW-002)', function () {
    it('gives the host the channels and live progress, without any answer', function () {
        $quiz = rtQuiz();
        $session = rtRun($quiz, [$this->student, $this->classmate]);
        [$placement] = rtPlacements($quiz);
        rtAnswer(rtParticipant($quiz), $placement, ['A'])->assertOk();

        $response = rtHostState($session)->assertOk()
            ->assertJsonPath('channels.session', LiveChannels::session($this->acme->id, $session->id))
            ->assertJsonPath('channels.host', LiveChannels::host($this->acme->id, $session->id))
            ->assertJsonPath('progress.session.status', 'running')
            ->assertJsonPath('results', null);

        expect($response->json('progress.session.server_now'))->not->toBeNull()
            ->and(collect($response->json('progress.participants'))->pluck('answered', 'student.id')->all())
            ->toBe([$this->student->id => 1, $this->classmate->id => 0])
            ->and($response->content())->not->toContain('option_ids')
            ->and($response->content())->not->toContain('is_correct')
            ->and($response->content())->not->toContain($this->student->email);
    });

    it('gives a student joining at 10:10 of a quiz ending 10:15 five minutes (UC-025)', function () {
        $quiz = rtQuiz();
        rtRun($quiz);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:10:00'));
        rtJoin($quiz, $this->classmate)->assertSessionHasNoErrors();

        $state = rtOwnState(rtParticipant($quiz, $this->classmate), $this->classmate)->assertOk()->json();

        expect($state['channels'])->toBe(['session' => LiveChannels::session($this->acme->id, rtSession($quiz)->id)])
            ->and($state['participation']['session']['remaining_seconds'])->toBe(300)
            ->and(CarbonImmutable::parse($state['participation']['session']['ends_at'])->utc()->toDateTimeString())->toBe('2026-11-02 10:15:00')
            ->and(CarbonImmutable::parse($state['participation']['session']['server_now'])->utc()->toDateTimeString())->toBe('2026-11-02 10:10:00');
    });

    it('lets a reconnecting student continue with their saved answers and the time left (UC-025)', function () {
        $quiz = rtQuiz([Question::factory(), Question::factory()]);
        rtRun($quiz);
        [$first] = rtPlacements($quiz);
        rtAnswer(rtParticipant($quiz), $first, ['A'])->assertOk();

        // Connection lost for a while; the snapshot is all the client needs.
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:07:30'));
        $state = rtOwnState(rtParticipant($quiz))->assertOk()->json('participation');

        expect($state['session']['remaining_seconds'])->toBe(450)
            ->and($state['participant']['submitted'])->toBeFalse()
            ->and($state['answers'])->toHaveCount(1)
            ->and($state['answers'][0]['quiz_question_id'])->toBe($first->id)
            ->and($state['answers'][0]['response']['option_ids'])->toBe($first->question->options->where('text', 'A')->pluck('id')->values()->all());
    });

    it('scores the answer changed from B to C before submitting (UC-025)', function () {
        $quiz = rtQuiz([Question::factory(), Question::factory()->withOptions([['A', false], ['B', false], ['C', true]])]);
        rtRun($quiz);
        [, $second] = rtPlacements($quiz);

        rtAnswer(rtParticipant($quiz), $second, ['B'])->assertOk();
        rtAnswer(rtParticipant($quiz), $second, ['C'])->assertOk();

        $c = $second->question->options->where('text', 'C')->pluck('id')->values()->all();
        expect(rtOwnState(rtParticipant($quiz))->json('participation.answers.0.response.option_ids'))->toBe($c);

        rtSubmit(rtParticipant($quiz))->assertSessionHasNoErrors();

        $state = rtOwnState(rtParticipant($quiz))->json('participation');
        expect($state['answers'][0]['is_correct'])->toBeTrue()
            ->and(LiveAnswer::query()->sole()->response['option_ids'])->toBe($c);
    });

    it('refuses changing an answer after submitting early (UC-025)', function () {
        $quiz = rtQuiz();
        rtRun($quiz, [$this->student, $this->classmate]);
        [$placement] = rtPlacements($quiz);
        rtAnswer(rtParticipant($quiz), $placement, ['A'])->assertOk();
        rtSubmit(rtParticipant($quiz))->assertSessionHasNoErrors();

        rtAnswer(rtParticipant($quiz), $placement, ['B'])->assertStatus(409);

        $state = rtOwnState(rtParticipant($quiz))->json('participation');
        expect($state['participant']['submitted'])->toBeTrue()
            ->and($state['answers'][0]['response']['option_ids'])->toBe($placement->question->options->where('text', 'A')->pluck('id')->values()->all());
    });

    it('never tells a student whether an answer is correct before the finish (UC-025)', function () {
        $quiz = rtQuiz();
        rtRun($quiz, [$this->student, $this->classmate]);
        [$placement] = rtPlacements($quiz);
        rtAnswer(rtParticipant($quiz), $placement, ['A'])->assertOk();
        rtSubmit(rtParticipant($quiz))->assertSessionHasNoErrors();

        $running = rtOwnState(rtParticipant($quiz))->assertOk();
        expect($running->content())->not->toContain('is_correct')
            ->and($running->content())->not->toContain('"points"')
            ->and($running->json('participation.participant.score'))->toBeNull()
            ->and($running->json('results'))->toBeNull();

        rtSubmit(rtParticipant($quiz, $this->classmate), $this->classmate)->assertSessionHasNoErrors();

        expect(rtOwnState(rtParticipant($quiz))->json('participation.answers.0'))->toHaveKey('is_correct');
    });

    it('does not count an answer arriving after the common end time (UC-025)', function () {
        $quiz = rtQuiz();
        rtRun($quiz);
        [$placement] = rtPlacements($quiz);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:15:01'));
        rtAnswer(rtParticipant($quiz), $placement, ['A'])->assertStatus(409);

        $state = rtOwnState(rtParticipant($quiz))->json('participation');
        expect($state['answers'])->toBe([])
            ->and($state['session']['status'])->toBe('finished')
            ->and($state['participant']['score'])->toBe('0.00');
    });

    it('shows 2 of 3 points for 2 of 3 correct options selected (UC-024)', function () {
        $quiz = rtQuiz([Question::factory()->multipleChoice()->withOptions([
            ['A', true], ['B', true], ['C', true], ['D', false],
        ])->state(['points' => '3.00'])]);
        rtRun($quiz);
        [$placement] = rtPlacements($quiz);

        rtAnswer(rtParticipant($quiz), $placement, ['A', 'B'])->assertOk();
        rtSubmit(rtParticipant($quiz))->assertSessionHasNoErrors();

        $state = rtOwnState(rtParticipant($quiz))->json();
        expect($state['participation']['answers'][0]['points'])->toBe('2.00')
            ->and($state['participation']['participant']['score'])->toBe('2.00')
            ->and($state['results']['own']['score'])->toBe('2.00');
    });

    it('shows neither teacher nor students a ranking or podium when leaderboards are disabled (UC-024)', function () {
        $this->acme->forceFill(['leaderboard_enabled' => false])->save();
        $quiz = rtQuiz();
        $session = rtRun($quiz, [$this->student, $this->classmate]);
        rtSubmit(rtParticipant($quiz))->assertSessionHasNoErrors();
        rtSubmit(rtParticipant($quiz, $this->classmate), $this->classmate)->assertSessionHasNoErrors();

        $student = rtOwnState(rtParticipant($quiz))->assertOk()->json();
        $host = rtHostState($session)->assertOk()->json();

        expect($student['results']['leaderboard'])->toBeFalse()
            ->and($student['results']['ranking'])->toBeNull()
            ->and($student['results']['podium'])->toBeNull()
            ->and($student['results']['own']['final_rank'])->toBeNull()
            ->and($student['participation']['participant']['final_rank'])->toBeNull()
            ->and($host['results']['ranking'])->toBeNull()
            ->and($host['results']['podium'])->toBeNull()
            ->and(collect($host['results']['participants'])->pluck('final_rank')->unique()->all())->toBe([null]);
    });

    it('shows the ranking by nickname and avatar only when leaderboards are enabled', function () {
        $quiz = rtQuiz();
        rtRun($quiz, [$this->student, $this->classmate]);
        rtSubmit(rtParticipant($quiz))->assertSessionHasNoErrors();
        rtSubmit(rtParticipant($quiz, $this->classmate), $this->classmate)->assertSessionHasNoErrors();

        $student = rtOwnState(rtParticipant($quiz))->assertOk();

        expect($student->json('results.ranking'))->toHaveCount(2)
            ->and(array_keys($student->json('results.ranking.0.student')))->toBe(['id', 'nickname', 'avatar'])
            ->and($student->content())->not->toContain($this->classmate->email)
            ->and($student->content())->not->toContain($this->classmate->name);
    });

    it('awards no grade to My results; LiveSessionFinished is what XP listens to (UC-025)', function () {
        Event::fake([LiveSessionFinished::class]);
        $quiz = rtQuiz();
        rtRun($quiz);
        rtSubmit(rtParticipant($quiz))->assertSessionHasNoErrors();

        expect(rtOwnState(rtParticipant($quiz))->json('participation.session.status'))->toBe('finished')
            ->and(QuizAttempt::query()->exists())->toBeFalse()
            ->and(app(ListMyResults::class)->handle($this->student))->toBe([]);

        Event::assertDispatchedTimes(LiveSessionFinished::class, 1);
    });

    it('refuses a student not enrolled: no join, no channel, no snapshot (UC-025, SEC-005)', function () {
        $quiz = rtQuiz();
        $session = rtRun($quiz);

        rtJoin($quiz, $this->outsider)->assertForbidden();

        expect(LiveParticipant::query()->where('student_id', $this->outsider->id)->exists())->toBeFalse();
        rtHostState($session, $this->outsider)->assertForbidden();

        rtSignChannels();
        rtSubscribe($this->outsider, 'private-'.LiveChannels::session($this->acme->id, $session->id))->assertForbidden();
        rtSubscribe($this->outsider, 'presence-'.LiveChannels::host($this->acme->id, $session->id))->assertForbidden();
    });

    it('answers unknown or foreign records with RFC 9457 problem details', function () {
        $globex = $this->createOrganization(['subdomain' => 'globex']);
        $foreign = $this->asTenant($globex, fn () => LiveSession::factory()->running()->create());

        rtHostState($foreign)->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 404);
    });
});

/**
 * Every live record as stored, to prove a refused request changed nothing.
 *
 * @return array<string, mixed>
 */
function rtStored(): array
{
    return [
        'sessions' => LiveSession::query()->withoutGlobalScopes()->orderBy('id')->get()->toArray(),
        'participants' => LiveParticipant::query()->withoutGlobalScopes()->orderBy('id')->get()->makeVisible('score')->toArray(),
        'answers' => LiveAnswer::query()->withoutGlobalScopes()->orderBy('id')->get()->makeVisible(['response', 'is_correct', 'points'])->toArray(),
    ];
}

describe('ownership of the snapshots', function () {
    beforeEach(function () {
        $this->quiz = rtQuiz();
        $this->session = rtRun($this->quiz, [$this->student, $this->classmate]);
        [$this->placement] = rtPlacements($this->quiz);
        rtAnswer(rtParticipant($this->quiz), $this->placement, ['A'])->assertOk();
        rtAnswer(rtParticipant($this->quiz, $this->classmate), $this->placement, ['B'], $this->classmate)->assertOk();
    });

    it('OWN-012: denies a student another student\'s participation and the host view, and changes nothing', function () {
        $before = rtStored();

        rtOwnState(rtParticipant($this->quiz, $this->classmate))->assertForbidden()
            ->assertHeader('Content-Type', 'application/problem+json');
        rtHostState($this->session, $this->student)->assertForbidden();
        rtAnswer(rtParticipant($this->quiz, $this->classmate), $this->placement, ['C'])->assertForbidden();
        rtSubmit(rtParticipant($this->quiz, $this->classmate))->assertForbidden();

        expect(rtStored())->toBe($before);
        rtOwnState(rtParticipant($this->quiz))->assertOk();
    });

    it('OWN-012: denies a teacher who is not the host every participation and the host view, and changes nothing', function () {
        $before = rtStored();

        rtHostState($this->session, $this->otherTeacher)->assertForbidden();
        rtOwnState(rtParticipant($this->quiz), $this->otherTeacher)->assertForbidden();
        rtAnswer(rtParticipant($this->quiz), $this->placement, ['C'], $this->otherTeacher)->assertForbidden();
        rtSubmit(rtParticipant($this->quiz), $this->otherTeacher)->assertForbidden();
        rtHostState($this->session, $this->admin)->assertForbidden();

        expect(rtStored())->toBe($before);
    });

    it('OWN-013: denies a student another student\'s answers in every snapshot', function () {
        $own = rtOwnState(rtParticipant($this->quiz))->assertOk()->json('participation.answers');
        $classmateOption = $this->placement->question->options->where('text', 'B')->value('id');

        expect($own)->toHaveCount(1)
            ->and(json_encode($own))->not->toContain($classmateOption);
    });

    it('OWN-013: gives the host only answered counts during the session, never a student\'s answers, and changes nothing', function () {
        $before = rtStored();

        rtOwnState(rtParticipant($this->quiz), $this->teacher)->assertForbidden();
        rtAnswer(rtParticipant($this->quiz), $this->placement, ['C'], $this->teacher)->assertForbidden();

        $host = rtHostState($this->session)->assertOk();
        foreach ($this->placement->question->options as $option) {
            expect($host->content())->not->toContain($option->id);
        }

        expect(rtStored())->toBe($before);
    });
});

describe('ENT-LiveSession state model', function () {
    it('rejects a transition not in the state model, keeps the state and broadcasts nothing', function () {
        Event::fake([LiveSessionChanged::class, LiveProgressUpdated::class]);
        $quiz = rtQuiz();
        $session = rtRun($quiz);

        // running -> cancelled and running -> running are not in the model.
        Event::fake([LiveSessionChanged::class, LiveProgressUpdated::class]);
        rtCancel($session)->assertSessionHasErrors('session');
        rtStart($session)->assertSessionHasErrors('session');

        expect($session->refresh()->status)->toBe(LiveSessionStatus::Running);
        Event::assertNotDispatched(LiveSessionChanged::class);
        rtHostState($session)->assertJsonPath('progress.session.status', 'running');
    });
});

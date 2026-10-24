<?php

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use App\Modules\Live\Enums\LiveSessionStatus;
use App\Modules\Live\Exceptions\InvalidLiveSessionTransition;
use App\Modules\Live\Models\LiveAnswer;
use App\Modules\Live\Models\LiveParticipant;
use App\Modules\Live\Models\LiveSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

/*
| OWN-012 (ENT-LiveParticipant) and OWN-013 (ENT-LiveAnswer): a student
| reaches only their own participation and answers; the host teacher sees
| all participants of their session, during it only how many questions
| each answered, after it the answers; nobody but the student changes
| them. Plus the state model of ENT-LiveSession and tenant isolation of
| the live tables.
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
    $this->admin = User::factory()->orgAdmin()->create();

    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
    Enrolment::factory()->for($space, 'courseSpace')->for($this->student, 'student')->create();
    Enrolment::factory()->for($space, 'courseSpace')->for($this->classmate, 'student')->create();

    // The other teacher hosts a session of their own course space.
    $otherSpace = CourseSpace::factory()->for($this->otherTeacher, 'teacher')->create();
    $otherQuiz = Quiz::factory()->live()
        ->for(Activity::factory()->for($otherSpace, 'courseSpace')->published()->create(), 'activity')
        ->withQuestions(1)
        ->create();
    LiveSession::factory()->for($otherQuiz, 'quiz')->for($this->otherTeacher, 'host')->running()->create();

    $this->quiz = Quiz::factory()->live()
        ->for(Activity::factory()->for($space, 'courseSpace')->published()->create(), 'activity')
        ->withQuestions(1)
        ->create();
    $this->session = LiveSession::factory()->for($this->quiz, 'quiz')->for($this->teacher, 'host')->running()->create();
    $this->placement = QuizQuestion::query()->where('quiz_id', $this->quiz->activity_id)->with('question.options')->sole();

    $this->own = LiveParticipant::factory()->for($this->session, 'session')->for($this->student, 'student')->create();
    $this->other = LiveParticipant::factory()->for($this->session, 'session')->for($this->classmate, 'student')->create();

    $this->ownAnswer = liveAnswerFor($this->own, 'A');
    $this->otherAnswer = liveAnswerFor($this->other, 'B');
});

function liveAnswerFor(LiveParticipant $participant, string $option): LiveAnswer
{
    $placement = test()->placement;

    return LiveAnswer::factory()->for($participant, 'participant')->create([
        'quiz_question_id' => $placement->id,
        'response' => ['option_ids' => $placement->question->options->where('text', $option)->pluck('id')->values()->all()],
    ]);
}

function ownUrl(string $path): string
{
    return test()->tenantUrl(test()->acme, ltrim($path, '/'));
}

/**
 * @return array<string, mixed>
 */
function liveSnapshot(): array
{
    return [
        'sessions' => LiveSession::query()->withoutGlobalScopes()->orderBy('id')->get()->toArray(),
        'participants' => LiveParticipant::query()->withoutGlobalScopes()->orderBy('id')->get()->makeVisible('score')->toArray(),
        'answers' => LiveAnswer::query()->withoutGlobalScopes()->orderBy('id')->get()->makeVisible(['response', 'is_correct', 'points'])->toArray(),
    ];
}

/**
 * Tries every write a user could attempt on the participation and its
 * answer over HTTP and returns the status codes.
 *
 * @return list<int>
 */
function tryWrites(User $actor, LiveParticipant $participant): array
{
    $placement = test()->placement;
    $option = $placement->question->options->pluck('id')->first();

    return [
        test()->actingAs($actor)->putJson(ownUrl("live/participants/{$participant->id}/answers/{$placement->id}"), ['option_ids' => [$option]])->status(),
        test()->actingAs($actor)->post(ownUrl("live/participants/{$participant->id}/submit"))->status(),
    ];
}

describe('OWN-012 ENT-LiveParticipant', function () {
    it('lets a student see and change only their own participation', function () {
        $gate = Gate::forUser($this->student);

        expect($gate->allows('view', $this->own))->toBeTrue()
            ->and($gate->allows('update', $this->own))->toBeTrue()
            ->and($gate->denies('view', $this->other))->toBeTrue()
            ->and($gate->denies('update', $this->other))->toBeTrue()
            ->and($gate->denies('host', $this->session))->toBeTrue();
    });

    it('denies a student every write to another student\'s participation and changes nothing', function () {
        $before = liveSnapshot();

        expect(tryWrites($this->student, $this->other))->toBe([403, 403])
            ->and(liveSnapshot())->toBe($before);
    });

    it('lets the host teacher see every participant of their session only, and change none', function () {
        $host = Gate::forUser($this->teacher);
        $otherSession = LiveSession::query()->where('host_id', $this->otherTeacher->id)->sole();
        $foreign = LiveParticipant::factory()->for($otherSession, 'session')->create();

        expect($host->allows('view', $this->own))->toBeTrue()
            ->and($host->allows('view', $this->other))->toBeTrue()
            ->and($host->denies('update', $this->own))->toBeTrue()
            ->and($host->denies('view', $foreign))->toBeTrue();
    });

    it('denies a teacher who is not the host every read and write and changes nothing', function () {
        $gate = Gate::forUser($this->otherTeacher);
        $before = liveSnapshot();

        expect($gate->denies('view', $this->own))->toBeTrue()
            ->and($gate->denies('host', $this->session))->toBeTrue()
            ->and(tryWrites($this->otherTeacher, $this->own))->toBe([403, 403])
            ->and(tryWrites($this->teacher, $this->own))->toBe([403, 403])
            ->and($this->actingAs($this->otherTeacher)->post(ownUrl("live/sessions/{$this->session->id}/cancel"))->status())->toBe(403)
            ->and(liveSnapshot())->toBe($before);
    });

    it('denies an org admin any individual participation', function () {
        $gate = Gate::forUser($this->admin);

        expect($gate->denies('view', $this->own))->toBeTrue()
            ->and($gate->denies('update', $this->own))->toBeTrue()
            ->and($gate->denies('host', $this->session))->toBeTrue();
    });
});

describe('OWN-013 ENT-LiveAnswer', function () {
    it('lets a student see and change only their own answers', function () {
        $gate = Gate::forUser($this->student);

        expect($gate->allows('view', $this->ownAnswer))->toBeTrue()
            ->and($gate->allows('update', $this->ownAnswer))->toBeTrue()
            ->and($gate->denies('view', $this->otherAnswer))->toBeTrue()
            ->and($gate->denies('update', $this->otherAnswer))->toBeTrue();
    });

    it('denies a student changing another student\'s answers and changes nothing', function () {
        $before = liveSnapshot();

        expect(tryWrites($this->student, $this->other))->toBe([403, 403])
            ->and(liveSnapshot())->toBe($before);
    });

    it('denies the host teacher the answers during the session and allows them after it finishes', function () {
        expect(Gate::forUser($this->teacher)->denies('view', $this->ownAnswer))->toBeTrue()
            ->and(Gate::forUser($this->teacher)->denies('update', $this->ownAnswer))->toBeTrue();

        $this->session->transitionTo(LiveSessionStatus::Finished);
        $this->session->save();
        $answer = LiveAnswer::query()->findOrFail($this->ownAnswer->id);

        expect(Gate::forUser($this->teacher)->allows('view', $answer))->toBeTrue()
            ->and(Gate::forUser($this->teacher)->denies('update', $answer))->toBeTrue();
    });

    it('denies a teacher who is not the host every read and write of the answers and changes nothing', function () {
        $this->session->transitionTo(LiveSessionStatus::Finished);
        $this->session->save();
        $answer = LiveAnswer::query()->findOrFail($this->ownAnswer->id);
        $before = liveSnapshot();

        expect(Gate::forUser($this->otherTeacher)->denies('view', $answer))->toBeTrue()
            ->and(Gate::forUser($this->otherTeacher)->denies('update', $answer))->toBeTrue()
            ->and(Gate::forUser($this->admin)->denies('view', $answer))->toBeTrue()
            ->and(tryWrites($this->otherTeacher, $this->own))->toBe([403, 403])
            ->and(liveSnapshot())->toBe($before);
    });
});

describe('ENT-LiveSession state model', function () {
    it('rejects a transition not in the state model and keeps the state', function (string $from, string $to) {
        $factory = LiveSession::factory()->for($this->otherTeacher, 'host');
        $factory = match ($from) {
            'running' => $factory->running(),
            'finished' => $factory->finished(),
            'cancelled' => $factory->cancelled(),
            default => $factory,
        };
        $session = $factory->create();

        expect(fn () => $session->transitionTo(LiveSessionStatus::from($to)))->toThrow(InvalidLiveSessionTransition::class);

        $session->status = LiveSessionStatus::from($to);
        expect(fn () => $session->save())->toThrow(InvalidLiveSessionTransition::class)
            ->and($session->status->value)->toBe($from)
            ->and(LiveSession::query()->findOrFail($session->id)->status->value)->toBe($from);
    })->with([
        ['lobby', 'finished'],
        ['running', 'lobby'],
        ['running', 'cancelled'],
        ['finished', 'lobby'],
        ['finished', 'running'],
        ['finished', 'cancelled'],
        ['cancelled', 'lobby'],
        ['cancelled', 'running'],
        ['cancelled', 'finished'],
    ]);

    it('allows the transitions of the state model', function (string $from, string $to) {
        $session = LiveSession::factory()->for($this->otherTeacher, 'host');
        $session = ($from === 'running' ? $session->running() : $session)->create();

        $session->transitionTo(LiveSessionStatus::from($to));
        $session->save();

        expect(LiveSession::query()->findOrFail($session->id)->status->value)->toBe($to);
    })->with([
        ['lobby', 'running'],
        ['lobby', 'cancelled'],
        ['running', 'finished'],
    ]);

    it('creates sessions only as lobby', function () {
        $session = new LiveSession;
        $session->quiz_id = $this->quiz->activity_id;
        $session->host_id = $this->teacher->id;
        $session->status = LiveSessionStatus::Running;

        expect(fn () => $session->save())->toThrow(InvalidLiveSessionTransition::class);
    });
});

describe('tenant isolation', function () {
    it('isolates live sessions per organisation', function () {
        $this->assertTenantIsolated(LiveSession::class, fn () => LiveSession::factory()->create());
    });

    it('isolates live participants per organisation', function () {
        $this->assertTenantIsolated(LiveParticipant::class, fn () => LiveParticipant::factory()->create());
    });

    it('isolates live answers per organisation', function () {
        $this->assertTenantIsolated(LiveAnswer::class, function (): LiveAnswer {
            $quiz = Quiz::factory()->live()->withQuestions(1)->create();
            $participant = LiveParticipant::factory()->for(LiveSession::factory()->for($quiz, 'quiz'), 'session')->create();

            return LiveAnswer::factory()->for($participant, 'participant')->create([
                'quiz_question_id' => $quiz->quizQuestions()->value('id'),
            ]);
        });
    });

    it('binds no session of another organisation in a route', function () {
        $globex = $this->createOrganization(['subdomain' => 'globex']);
        $foreign = $this->asTenant($globex, fn () => LiveSession::factory()->create());

        $this->actingAs($this->teacher)
            ->post(ownUrl("live/sessions/{$foreign->id}/start"))
            ->assertNotFound();
    });
});

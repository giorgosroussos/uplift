<?php

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Events\TopicProposalDecided;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\TopicProposal;
use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Actions\UpdateNotificationPreference;
use App\Modules\Notifications\Enums\NotificationKind;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Notifications\TopicProposalDecidedEmail;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

/*
| DEC-001 / INT-001 (P4-T7): when the teacher approves or rejects a topic
| proposal (TopicProposalDecided), the proposing student, or every current
| member of the proposing group, gets one in-app topic_proposal_decided
| entry and, unless they turned notification emails off, one queued email
| in their locale. Neither carries the description nor the teacher's
| comment.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

    $this->acme = $this->createOrganization(['subdomain' => 'acme', 'default_locale' => 'el']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create(['email' => 'alice@acme.test', 'locale' => 'en']);
    $this->classmate = User::factory()->student()->create(['email' => 'bob@acme.test', 'locale' => null]);
    $this->former = User::factory()->student()->create(['email' => 'carol@acme.test']);
    $this->bystander = User::factory()->student()->create();

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);

    foreach ([$this->student, $this->classmate, $this->former, $this->bystander] as $enrolled) {
        Enrolment::factory()->for($this->space, 'courseSpace')->for($enrolled, 'student')->create();
    }

    $this->activity = Activity::factory()->ofKind(ActivityKind::TopicProposal)->for($this->space, 'courseSpace')
        ->published()->create(['title' => 'Thesis topics']);
});

function tpnUrl(string $path): string
{
    return test()->tenantUrl(test()->acme, ltrim($path, '/'));
}

/**
 * @param  array<string, mixed>  $input
 */
function tpnPropose(User $student, Activity $activity, array $input = []): TopicProposal
{
    test()->actingAs($student)->from(tpnUrl("proposals/{$activity->id}"))
        ->post(tpnUrl("proposals/{$activity->id}"), [
            'title' => 'Graph colouring',
            'description' => 'Secret heuristics',
            ...$input,
        ])
        ->assertSessionHasNoErrors();

    return TopicProposal::query()->where('topic_activity_id', $activity->id)->latest()->firstOrFail();
}

function tpnDecide(TopicProposal $proposal, string $decision, ?string $comment = null): void
{
    $activityId = $proposal->topic_activity_id;

    test()->actingAs(test()->teacher)->from(tpnUrl("proposals/{$activityId}"))
        ->put(tpnUrl("proposals/{$activityId}/{$proposal->id}/decision"), array_filter([
            'decision' => $decision,
            'comment' => $comment,
        ]))
        ->assertSessionHasNoErrors();
}

/**
 * Student is the owner; classmate a member; former has left.
 */
function tpnGroup(): Group
{
    $test = test();
    $group = Group::factory()->for($test->space, 'courseSpace')->ownedBy($test->student)->create(['name' => 'Team A']);
    GroupMembership::factory()->for($group, 'group')->for($test->student, 'student')->create(['status' => GroupMembershipStatus::Member]);

    foreach ([$test->classmate, $test->former] as $member) {
        GroupMembership::factory()->for($group, 'group')->for($member, 'student')->create()
            ->transitionTo(GroupMembershipStatus::Member);
    }

    return $group;
}

/**
 * @return Collection<int, Email>
 */
function tpnMails(): Collection
{
    /** @var Collection<int, SentMessage> $messages */
    $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();

    return $messages->map(function (SentMessage $message): Email {
        $original = $message->getOriginalMessage();
        assert($original instanceof Email);

        return $original;
    })->values();
}

function tpnEntries(): int
{
    return Notification::query()->where('kind', NotificationKind::TopicProposalDecided->value)->count();
}

it('gives the proposing student one in-app entry and one queued email when the proposal is approved', function () {
    NotificationFacade::fake();
    $proposal = tpnPropose($this->student, $this->activity);

    // Proposing notifies nobody.
    expect(Notification::query()->count())->toBe(0);

    tpnDecide($proposal, 'approve');

    $entry = Notification::query()->sole();
    expect($entry->user_id)->toBe($this->student->id)
        ->and($entry->kind)->toBe(NotificationKind::TopicProposalDecided)
        ->and($entry->organization_id)->toBe($this->acme->id)
        ->and($entry->read_at)->toBeNull()
        // Ids, the decision and title snapshots only.
        ->and($entry->data)->toEqual([
            'course_space_id' => $this->space->id,
            'activity_id' => $this->activity->id,
            'topic_proposal_id' => $proposal->id,
            'status' => 'approved',
            'course_space_title' => 'Algorithms',
            'activity_title' => 'Thesis topics',
        ]);

    expect(new TopicProposalDecidedEmail('u', 'p', 'approved', 's', 't', 'l'))->toBeInstanceOf(ShouldQueue::class);

    NotificationFacade::assertSentOnDemandTimes(TopicProposalDecidedEmail::class, 1);
    NotificationFacade::assertSentOnDemand(
        TopicProposalDecidedEmail::class,
        fn (TopicProposalDecidedEmail $email, array $channels, AnonymousNotifiable $notifiable, ?string $locale): bool => $notifiable->routes['mail'] === 'alice@acme.test'
            && $locale === 'en'
            && $channels === ['mail']
            && $email->status === 'approved'
            && $email->url === 'http://acme.uplift.test/proposals/'.$this->activity->id,
    );
});

it('sends the rejection email in the student\'s locale without the description or the teacher\'s comment', function () {
    $this->student->update(['locale' => null]);
    $proposal = tpnPropose($this->student, $this->activity);

    tpnDecide($proposal, 'reject', 'Private teacher remark');

    expect(Notification::query()->sole()->data['status'])->toBe('rejected');

    $mail = tpnMails()->sole();
    expect($mail->getTo()[0]->getAddress())->toBe('alice@acme.test')
        // The organisation's default_locale (el).
        ->and($mail->getSubject())->toBe('Η πρόταση θέματος για το Thesis topics απορρίφθηκε')
        ->and((string) $mail->getTextBody())->toContain('http://acme.uplift.test/proposals/'.$this->activity->id)
        ->not->toContain('Private teacher remark')
        ->not->toContain('Secret heuristics')
        ->and((string) $mail->getHtmlBody())->not->toContain('Private teacher remark')
        ->not->toContain('Secret heuristics');

    $this->student->update(['locale' => 'en']);
    $other = Activity::factory()->ofKind(ActivityKind::TopicProposal)->for($this->space, 'courseSpace')
        ->published()->create(['title' => 'Essay topics']);
    tpnDecide(tpnPropose($this->student, $other), 'approve');

    expect(tpnMails()->last()?->getSubject())->toBe('Your topic proposal for Essay topics was approved');
});

it('notifies every current member of the proposing group, not former members or others', function () {
    NotificationFacade::fake();
    $this->activity->update(['is_group' => true, 'group_size' => 3]);
    $group = tpnGroup();
    $proposal = tpnPropose($this->student, $this->activity, ['group_id' => $group->id]);

    GroupMembership::query()->where('group_id', $group->id)->where('student_id', $this->former->id)->firstOrFail()
        ->transitionTo(GroupMembershipStatus::Left);

    tpnDecide($proposal, 'approve');

    expect(Notification::query()->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$this->student->id, $this->classmate->id])->sort()->values()->all());

    NotificationFacade::assertSentOnDemandTimes(TopicProposalDecidedEmail::class, 2);
    NotificationFacade::assertSentOnDemand(
        TopicProposalDecidedEmail::class,
        fn (TopicProposalDecidedEmail $email, array $channels, AnonymousNotifiable $notifiable, ?string $locale): bool => $notifiable->routes['mail'] === 'bob@acme.test' && $locale === 'el',
    );
});

it('notifies nobody when the decision is refused', function () {
    NotificationFacade::fake();
    $proposal = tpnPropose($this->student, $this->activity);

    // A rejection needs a comment.
    $this->actingAs($this->teacher)->from(tpnUrl("proposals/{$this->activity->id}"))
        ->put(tpnUrl("proposals/{$this->activity->id}/{$proposal->id}/decision"), ['decision' => 'reject'])
        ->assertSessionHasErrors();

    // Another teacher may not decide.
    $other = User::factory()->teacher()->create();
    $this->actingAs($other)
        ->put(tpnUrl("proposals/{$this->activity->id}/{$proposal->id}/decision"), ['decision' => 'approve']);

    expect(tpnEntries())->toBe(0);
    NotificationFacade::assertNothingSent();
});

it('notifies nothing for an event of a pending proposal', function () {
    NotificationFacade::fake();
    $proposal = tpnPropose($this->student, $this->activity);

    TopicProposalDecided::dispatch($this->acme->id, $proposal->id, 'approved');

    expect(tpnEntries())->toBe(0);
    NotificationFacade::assertNothingSent();
});

it('sends no email to a student who turned notification emails off, but still the in-app entry', function () {
    app(UpdateNotificationPreference::class)->handle($this->student, false);
    NotificationFacade::fake();

    tpnDecide(tpnPropose($this->student, $this->activity), 'approve');

    expect(tpnEntries())->toBe(1);
    NotificationFacade::assertNothingSent();
});

it('opens the proposals page from the notification while the student may view it, and 404 once unenrolled or deleted', function () {
    NotificationFacade::fake();
    $this->activity->update(['is_group' => true, 'group_size' => 3]);
    $group = tpnGroup();
    tpnDecide(tpnPropose($this->student, $this->activity, ['group_id' => $group->id]), 'approve');

    $forStudent = Notification::query()->where('user_id', $this->student->id)->sole();
    $forClassmate = Notification::query()->where('user_id', $this->classmate->id)->sole();

    $this->actingAs($this->student)
        ->post(tpnUrl("notifications/{$forStudent->id}/open"))
        ->assertRedirect('/proposals/'.$this->activity->id);
    expect($forStudent->fresh()?->read_at)->not->toBeNull();

    // Another user's notification cannot be opened.
    $this->actingAs($this->bystander)
        ->post(tpnUrl("notifications/{$forClassmate->id}/open"))
        ->assertForbidden();
    expect($forClassmate->fresh()?->read_at)->toBeNull();

    Enrolment::query()->where('student_id', $this->student->id)->delete();
    $this->actingAs($this->student)
        ->post(tpnUrl("notifications/{$forStudent->id}/open"))
        ->assertNotFound();

    // SCOPE-027: a deleted activity is 404, also from an existing notification.
    $this->activity->delete();
    $this->actingAs($this->classmate)
        ->post(tpnUrl("notifications/{$forClassmate->id}/open"))
        ->assertNotFound();
});

describe('INT-001: EXT-002 unavailable or timing out', function () {
    it('queues the email with timeout 10s and 5 retries with backoff 1m, 5m, 15m, 1h, 6h', function () {
        $proposal = tpnPropose($this->student, $this->activity);
        Queue::fake();

        tpnDecide($proposal, 'approve');

        // The in-app entry exists although no email has been sent yet.
        expect(tpnEntries())->toBe(1);

        Queue::assertPushed(SendQueuedNotifications::class, 1);
        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof TopicProposalDecidedEmail
            && $job->tries === 6
            && $job->timeout === 10
            && $job->backoff() === [60, 300, 900, 3600, 21600]);
    });

    it('keeps the in-app entry and the decision when the mail transport fails', function () {
        $proposal = tpnPropose($this->student, $this->activity);

        Mail::extend('failing', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Connection timed out');
            }

            public function __toString(): string
            {
                return 'failing';
            }
        });
        config(['mail.mailers.failing' => ['transport' => 'failing'], 'mail.default' => 'failing']);
        Log::spy();

        tpnDecide($proposal, 'approve');

        expect($proposal->fresh()?->status->value)->toBe('approved')
            ->and(tpnEntries())->toBe(1);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'notifications.email_failed'
            && $context['kind'] === 'topic_proposal_decided'
            && $context['user_id'] === $this->student->id
            && $context['topic_proposal_id'] === $proposal->id
            && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'alice@acme.test'));
    });

    it('does not block the decision and still creates the in-app entry when the queue rejects the email', function () {
        $proposal = tpnPropose($this->student, $this->activity);
        Queue::shouldReceive('connection')->andThrow(new RuntimeException('queue unavailable'));
        Log::spy();

        tpnDecide($proposal, 'approve');

        expect($proposal->fresh()?->status->value)->toBe('approved')
            ->and(tpnEntries())->toBe(1);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'notifications.email_not_queued'
            && $context['kind'] === 'topic_proposal_decided'
            && $context['topic_proposal_id'] === $proposal->id
            && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), '@acme.test'));
    });
});

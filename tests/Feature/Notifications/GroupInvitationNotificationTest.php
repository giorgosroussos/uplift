<?php

use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Events\GroupInvitationCreated;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Actions\UpdateNotificationPreference;
use App\Modules\Notifications\Enums\NotificationKind;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Notifications\GroupInvitationEmail;
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
| DEC-001 / INT-001 / SCOPE-025 (P4-T7): an invitation to a group
| (GroupInvitationCreated) gives the invitee one in-app group_invitation
| entry and, unless they turned notification emails off, one queued email
| in their locale with the space, the group name and a link to the group;
| never who invited them.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme', 'default_locale' => 'el']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->owner = User::factory()->student()->create(['name' => 'Olivia Owner', 'email' => 'owner@acme.test']);
    $this->invitee = User::factory()->student()->create(['email' => 'xena@acme.test', 'locale' => 'en']);
    $this->other = User::factory()->student()->create(['email' => 'yuri@acme.test', 'locale' => null]);

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);

    foreach ([$this->owner, $this->invitee, $this->other] as $student) {
        Enrolment::factory()->for($this->space, 'courseSpace')->for($student, 'student')->create();
    }

    $this->actingAs($this->owner)
        ->post($this->tenantUrl($this->acme, "courses/{$this->space->id}/groups"), ['name' => 'Team A'])
        ->assertSessionHasNoErrors();
    $this->group = Group::query()->where('name', 'Team A')->sole();
});

function ginGroupUrl(string $path = ''): string
{
    $test = test();

    return $test->tenantUrl($test->acme, rtrim("courses/{$test->space->id}/groups/{$test->group->id}/{$path}", '/'));
}

function ginInvite(User $student, ?User $by = null): GroupMembership
{
    $test = test();

    $test->actingAs($by ?? $test->owner)
        ->post(ginGroupUrl('invitations'), ['student_id' => $student->id])
        ->assertSessionHasNoErrors();

    return GroupMembership::query()
        ->where('group_id', $test->group->id)
        ->where('student_id', $student->id)
        ->where('status', GroupMembershipStatus::Invited)
        ->sole();
}

/**
 * @return Collection<int, Email>
 */
function ginMails(): Collection
{
    /** @var Collection<int, SentMessage> $messages */
    $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();

    return $messages->map(function (SentMessage $message): Email {
        $original = $message->getOriginalMessage();
        assert($original instanceof Email);

        return $original;
    })->values();
}

function ginEntries(): int
{
    return Notification::query()->where('kind', NotificationKind::GroupInvitation->value)->count();
}

it('gives the invitee one in-app entry and one queued email, and the inviter and others none', function () {
    NotificationFacade::fake();

    $invitation = ginInvite($this->invitee);

    $entry = Notification::query()->sole();
    expect($entry->user_id)->toBe($this->invitee->id)
        ->and($entry->kind)->toBe(NotificationKind::GroupInvitation)
        ->and($entry->organization_id)->toBe($this->acme->id)
        ->and($entry->read_at)->toBeNull()
        // Ids and snapshots only, never who invited them.
        ->and($entry->data)->toEqual([
            'course_space_id' => $this->space->id,
            'group_id' => $this->group->id,
            'group_membership_id' => $invitation->id,
            'course_space_title' => 'Algorithms',
            'group_name' => 'Team A',
        ]);

    expect(new GroupInvitationEmail('u', 'm', 's', 'g', 'l'))->toBeInstanceOf(ShouldQueue::class);

    NotificationFacade::assertSentOnDemandTimes(GroupInvitationEmail::class, 1);
    NotificationFacade::assertSentOnDemand(
        GroupInvitationEmail::class,
        fn (GroupInvitationEmail $email, array $channels, AnonymousNotifiable $notifiable, ?string $locale): bool => $notifiable->routes['mail'] === 'xena@acme.test'
            && $locale === 'en'
            && $channels === ['mail']
            && $email->groupMembershipId === $invitation->id
            && $email->url === "http://acme.uplift.test/courses/{$this->space->id}/groups/{$this->group->id}",
    );
});

it('sends the email with the space, the group name and the link, in the user\'s locale, else the organisation\'s', function () {
    ginInvite($this->invitee);
    ginInvite($this->other);

    $messages = ginMails();
    expect($messages)->toHaveCount(2);

    $to = fn (Email $email): string => $email->getTo()[0]->getAddress();
    $forXena = $messages->first(fn (Email $email): bool => $to($email) === 'xena@acme.test');
    $forYuri = $messages->first(fn (Email $email): bool => $to($email) === 'yuri@acme.test');

    expect($forXena->getSubject())->toBe('Invitation to the group Team A in Algorithms')
        ->and($forYuri->getSubject())->toBe('Πρόσκληση στην ομάδα Team A του Algorithms');

    foreach ([$forXena, $forYuri] as $email) {
        expect((string) $email->getTextBody())
            ->toContain("http://acme.uplift.test/courses/{$this->space->id}/groups/{$this->group->id}")
            ->not->toContain('Olivia Owner')
            ->not->toContain('owner@acme.test');
    }
});

it('notifies nobody when the invitation is refused, or when accepting, declining or withdrawing', function () {
    NotificationFacade::fake();
    $outsider = User::factory()->student()->create();

    // Not enrolled in the space.
    $this->actingAs($this->owner)->post(ginGroupUrl('invitations'), ['student_id' => $outsider->id])
        ->assertSessionHasErrors();
    // Only members invite.
    $this->actingAs($this->other)->post(ginGroupUrl('invitations'), ['student_id' => $this->invitee->id])
        ->assertForbidden();

    expect(ginEntries())->toBe(0);

    $invitation = ginInvite($this->invitee);
    $declined = ginInvite($this->other);

    $this->actingAs($this->invitee)->post(ginGroupUrl("invitations/{$invitation->id}/accept"))->assertSessionHasNoErrors();
    $this->actingAs($this->other)->post(ginGroupUrl("invitations/{$declined->id}/decline"))->assertSessionHasNoErrors();

    expect(ginEntries())->toBe(2)
        ->and(Notification::query()->count())->toBe(2);
    NotificationFacade::assertSentOnDemandTimes(GroupInvitationEmail::class, 2);
});

it('notifies nothing for an invitation no longer pending when the event is handled', function () {
    NotificationFacade::fake();
    $invitation = ginInvite($this->invitee);
    Notification::query()->delete();
    $invitation->transitionTo(GroupMembershipStatus::Withdrawn);

    GroupInvitationCreated::dispatch($this->acme->id, $invitation->id);

    expect(ginEntries())->toBe(0);
    NotificationFacade::assertSentOnDemandTimes(GroupInvitationEmail::class, 1);
});

it('sends no email to a student who turned notification emails off, but still the in-app entry', function () {
    app(UpdateNotificationPreference::class)->handle($this->invitee, false);
    NotificationFacade::fake();

    ginInvite($this->invitee);

    expect(ginEntries())->toBe(1);
    NotificationFacade::assertNothingSent();
});

it('opens the group from the notification while invited or a member, and 404 once declined or the group is deleted', function () {
    NotificationFacade::fake();
    $invitation = ginInvite($this->invitee);
    $declined = ginInvite($this->other);

    $forXena = Notification::query()->where('user_id', $this->invitee->id)->sole();
    $forYuri = Notification::query()->where('user_id', $this->other->id)->sole();
    $target = "/courses/{$this->space->id}/groups/{$this->group->id}";

    $this->actingAs($this->invitee)->post($this->tenantUrl($this->acme, "notifications/{$forXena->id}/open"))
        ->assertRedirect($target);
    expect($forXena->fresh()?->read_at)->not->toBeNull();

    // Another user's notification cannot be opened.
    $this->actingAs($this->invitee)->post($this->tenantUrl($this->acme, "notifications/{$forYuri->id}/open"))
        ->assertForbidden();

    $this->actingAs($this->invitee)->post(ginGroupUrl("invitations/{$invitation->id}/accept"))->assertSessionHasNoErrors();
    $this->actingAs($this->invitee)->post($this->tenantUrl($this->acme, "notifications/{$forXena->id}/open"))
        ->assertRedirect($target);

    $this->actingAs($this->other)->post(ginGroupUrl("invitations/{$declined->id}/decline"))->assertSessionHasNoErrors();
    $this->actingAs($this->other)->post($this->tenantUrl($this->acme, "notifications/{$forYuri->id}/open"))
        ->assertNotFound();

    $this->group->delete();
    $this->actingAs($this->invitee)->post($this->tenantUrl($this->acme, "notifications/{$forXena->id}/open"))
        ->assertNotFound();
});

describe('INT-001: EXT-002 unavailable or timing out', function () {
    it('queues the email with timeout 10s and 5 retries with backoff 1m, 5m, 15m, 1h, 6h', function () {
        Queue::fake();

        ginInvite($this->invitee);

        // The in-app entry exists although no email has been sent yet.
        expect(ginEntries())->toBe(1);

        Queue::assertPushed(SendQueuedNotifications::class, 1);
        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof GroupInvitationEmail
            && $job->tries === 6
            && $job->timeout === 10
            && $job->backoff() === [60, 300, 900, 3600, 21600]);
    });

    it('keeps the in-app entry and the invitation when the mail transport fails', function () {
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

        $invitation = ginInvite($this->invitee);

        expect($invitation->isInvited())->toBeTrue()
            ->and(ginEntries())->toBe(1);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'notifications.email_failed'
            && $context['kind'] === 'group_invitation'
            && $context['user_id'] === $this->invitee->id
            && $context['group_membership_id'] === $invitation->id
            && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'xena@acme.test'));
    });

    it('does not block the invitation and still creates the in-app entry when the queue rejects the email', function () {
        Queue::shouldReceive('connection')->andThrow(new RuntimeException('queue unavailable'));
        Log::spy();

        $invitation = ginInvite($this->invitee);

        expect(ginEntries())->toBe(1);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'notifications.email_not_queued'
            && $context['kind'] === 'group_invitation'
            && $context['group_membership_id'] === $invitation->id
            && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), '@acme.test'));
    });
});

<?php

use App\Modules\Identity\Database\Factories\InvitationFactory;
use App\Modules\Identity\Enums\InvitationStatus;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\InvitationNotification;
use App\Modules\Tenancy\Support\OrganizationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/*
| DATA-015 — the invitation email fails after its last retry (INT-001):
| email_failed_at is set and the Users page shows 'Email not delivered' with
| Resend. Org admins of the organisation resend pending or expired
| invitations with a new token and a fresh 7-day link.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme', 'name' => 'Acme School']);
    $this->beta = $this->createOrganization(['subdomain' => 'beta']);

    $this->admin = User::factory()->for($this->acme, 'organization')->orgAdmin()->create();
    $this->otherAdmin = User::factory()->for($this->acme, 'organization')->orgAdmin()->create();
    $this->teacher = User::factory()->for($this->acme, 'organization')->teacher()->create();
    $this->betaAdmin = User::factory()->for($this->beta, 'organization')->orgAdmin()->create();
});

/**
 * @return array{0: Invitation, 1: string}
 */
function resendableInvitation(mixed $organization, ?Closure $factory = null): array
{
    $builder = Invitation::factory()->for($organization, 'organization');
    $invitation = ($factory ? $factory($builder) : $builder)->create();

    return [$invitation, (string) InvitationFactory::$lastToken];
}

function storedInvitation(Invitation $invitation): Invitation
{
    return Invitation::query()->withoutGlobalScope(OrganizationScope::class)->findOrFail($invitation->getKey());
}

/**
 * @return array<string, mixed>
 */
function invitationColumns(Invitation $invitation): array
{
    return (array) DB::table('invitations')->where('id', $invitation->getKey())->first();
}

function resendUrl(mixed $organization, Invitation $invitation): string
{
    return test()->tenantUrl($organization, "invitations/{$invitation->getKey()}/resend");
}

function invitationLink(Invitation $invitation, string $token): string
{
    return test()->tenantUrl($invitation->organization, "invitation/{$invitation->getKey()}/{$token}");
}

/**
 * Runs the next queued job once, as the queue worker does.
 */
function runInvitationJob(): void
{
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0, '--queue' => 'default']);
}

/**
 * The invitation email of the last resend, from the faked notifications.
 */
function sentInvitationToken(): string
{
    $sent = Notification::sent(new AnonymousNotifiable, InvitationNotification::class);

    return $sent->sole()->token;
}

describe('delivery failure (DATA-015, INT-001)', function () {
    beforeEach(function () {
        // A mail transport that always fails, and a real (database) queue so
        // that the INT-001 retries run as they do in production.
        Mail::extend('failing', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Connection refused');
            }

            public function __toString(): string
            {
                return 'failing://';
            }
        });
        config([
            'mail.mailers.failing' => ['transport' => 'failing'],
            'mail.default' => 'failing',
            'queue.default' => 'database',
        ]);
    });

    it('sets email_failed_at only after the last retry and shows Email not delivered with Resend', function () {
        $this->actingAs($this->admin)
            ->post($this->tenantUrl($this->acme, 'invitations'), ['email' => 'unreachable@example.com', 'role' => 'student'])
            ->assertSessionHasNoErrors();

        $invitation = $this->asTenant($this->acme, fn () => Invitation::query()->sole());

        // One attempt plus 4 retries fail: not yet marked.
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            runInvitationJob();

            expect(storedInvitation($invitation)->email_failed_at)->toBeNull()
                ->and(DB::table('jobs')->count())->toBe(1);

            $this->travel(7)->hours();
        }

        // The 5th retry fails too.
        runInvitationJob();

        expect(DB::table('jobs')->count())->toBe(0)
            ->and(DB::table('failed_jobs')->count())->toBe(1)
            ->and(storedInvitation($invitation)->email_failed_at)->not->toBeNull()
            ->and(storedInvitation($invitation)->status)->toBe(InvitationStatus::Pending);

        $this->actingAs($this->admin)
            ->get($this->tenantUrl($this->acme, 'users'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Identity/Users')
                ->where('invitations.0.id', $invitation->getKey())
                ->where('invitations.0.email_failed', true));

        expect(__('users.invitations.email_failed', [], 'en'))->toBe('Email not delivered')
            ->and(__('users.invitations.resend', [], 'en'))->toBe('Resend');
    });

    it('logs ids only when the email is not delivered', function () {
        $logged = [];
        Log::listen(function ($message) use (&$logged) {
            $logged[] = $message->message.' '.json_encode($message->context);
        });
        [$invitation, $token] = resendableInvitation($this->acme, fn ($f) => $f->state(['email' => 'hidden.person@example.com']));

        (new InvitationNotification($invitation->getKey(), invitationLink($invitation, $token), 'Acme School', 'student', 7, $token))
            ->failed(new TransportException('Connection refused'));

        expect(storedInvitation($invitation)->email_failed_at)->not->toBeNull();
        expect(collect($logged)->contains(fn (string $line) => str_contains($line, $invitation->getKey())))->toBeTrue();
        foreach ($logged as $line) {
            expect($line)->not->toContain('hidden.person@example.com')->not->toContain($token);
        }
    });

    it('does not mark an invitation that was resent before the old email finally failed', function () {
        [$invitation, $oldToken] = resendableInvitation($this->acme);
        Notification::fake();

        $this->actingAs($this->admin)->post(resendUrl($this->acme, $invitation))->assertSessionHasNoErrors();

        (new InvitationNotification($invitation->getKey(), invitationLink($invitation, $oldToken), 'Acme School', 'student', 7, $oldToken))
            ->failed(new TransportException('Connection refused'));

        expect(storedInvitation($invitation)->email_failed_at)->toBeNull();
    });

    it('does not show Email not delivered for an invitation whose email went out', function () {
        resendableInvitation($this->acme);

        $this->actingAs($this->admin)
            ->get($this->tenantUrl($this->acme, 'users'))
            ->assertInertia(fn (Assert $page) => $page->where('invitations.0.email_failed', false));
    });
});

describe('resending (DATA-015)', function () {
    it('issues a new link valid for 7 days, clears email_failed_at and queues one email', function () {
        [$invitation, $oldToken] = resendableInvitation($this->acme, fn ($f) => $f->sentDaysAgo(3));
        storedInvitation($invitation)->markEmailFailed();
        Notification::fake();

        // Any org admin of the organisation may resend, not only the inviter.
        $this->actingAs($this->otherAdmin)
            ->post(resendUrl($this->acme, $invitation))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $fresh = storedInvitation($invitation);
        expect($fresh->status)->toBe(InvitationStatus::Pending)
            ->and($fresh->email_failed_at)->toBeNull()
            ->and($fresh->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()))->toBeTrue();

        Notification::assertSentTimes(InvitationNotification::class, 1);
        Notification::assertSentTo(
            new AnonymousNotifiable,
            InvitationNotification::class,
            fn (InvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === $invitation->email
                && $notification->invitationId === $invitation->getKey(),
        );
        $newToken = sentInvitationToken();
        expect($newToken)->not->toBe($oldToken);

        auth()->guard('web')->logout();
        $this->get(invitationLink($invitation, $oldToken))->assertNotFound();
        $this->get(invitationLink($invitation, $newToken))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/AcceptInvitation'));
    });

    it('makes an expired invitation pending again with a working link', function () {
        [$invitation] = resendableInvitation($this->acme, fn ($f) => $f->sentDaysAgo(10)->inStatus(InvitationStatus::Expired));
        Notification::fake();

        $this->actingAs($this->admin)->post(resendUrl($this->acme, $invitation))->assertSessionHasNoErrors();

        expect(storedInvitation($invitation)->status)->toBe(InvitationStatus::Pending)
            ->and(storedInvitation($invitation)->expires_at->isFuture())->toBeTrue();
        Notification::assertSentTimes(InvitationNotification::class, 1);

        auth()->guard('web')->logout();
        $this->get(invitationLink($invitation, sentInvitationToken()))->assertOk();
    });

    it('resends a pending invitation whose link ran out before its expiry was recorded', function () {
        [$invitation] = resendableInvitation($this->acme, fn ($f) => $f->sentDaysAgo(8));
        Notification::fake();

        $this->actingAs($this->admin)->post(resendUrl($this->acme, $invitation))->assertSessionHasNoErrors();

        expect(storedInvitation($invitation)->expires_at->isFuture())->toBeTrue();
        Notification::assertSentTimes(InvitationNotification::class, 1);
    });

    it('lists expired invitations with Resend but without Revoke', function () {
        [$invitation] = resendableInvitation($this->acme, fn ($f) => $f->sentDaysAgo(10)->inStatus(InvitationStatus::Expired));

        $this->actingAs($this->admin)
            ->get($this->tenantUrl($this->acme, 'users'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitations.0.id', $invitation->getKey())
                ->where('invitations.0.expired', true)
                ->where('invitations.0.revocable', false));
    });

    it('cannot resend an accepted or revoked invitation and changes nothing', function (InvitationStatus $status) {
        [$invitation] = resendableInvitation($this->acme, fn ($f) => $f->inStatus($status));
        $before = invitationColumns($invitation);
        Notification::fake();

        $this->actingAs($this->admin)
            ->post(resendUrl($this->acme, $invitation))
            ->assertSessionHasErrors('invitation');

        expect(invitationColumns($invitation))->toBe($before);
        Notification::assertNothingSent();
    })->with([InvitationStatus::Accepted, InvitationStatus::Revoked]);

    it('refuses to resend when the email was registered on the platform in the meantime', function () {
        [$invitation] = resendableInvitation($this->acme, fn ($f) => $f->state(['email' => 'taken@example.com']));
        User::factory()->for($this->beta, 'organization')->create(['email' => 'taken@example.com']);
        $before = invitationColumns($invitation);
        Notification::fake();

        $this->actingAs($this->admin)->post(resendUrl($this->acme, $invitation))->assertSessionHasErrors('invitation');

        expect(invitationColumns($invitation))->toBe($before);
        Notification::assertNothingSent();
    });

    it('refuses to resend when the email was invited again in the meantime', function () {
        [$old] = resendableInvitation($this->acme, fn ($f) => $f->state(['email' => 'again@example.com'])->sentDaysAgo(10)->inStatus(InvitationStatus::Expired));
        resendableInvitation($this->acme, fn ($f) => $f->state(['email' => 'again@example.com']));
        $before = invitationColumns($old);
        Notification::fake();

        $this->actingAs($this->admin)->post(resendUrl($this->acme, $old))->assertSessionHasErrors('invitation');

        expect(invitationColumns($old))->toBe($before);
        Notification::assertNothingSent();
    });
});

describe('who may resend (DATA-015, OWN-002)', function () {
    it('refuses an org admin of beta and changes nothing', function () {
        [$invitation] = resendableInvitation($this->acme);
        storedInvitation($invitation)->markEmailFailed();
        $before = invitationColumns($invitation);
        Notification::fake();

        $this->actingAs($this->betaAdmin);
        // On beta's subdomain acme's invitation does not exist.
        $this->post(resendUrl($this->beta, $invitation))->assertNotFound();
        // On acme's subdomain the policy denies beta's admin.
        $this->post(resendUrl($this->acme, $invitation))->assertForbidden();

        expect(invitationColumns($invitation))->toBe($before);
        Notification::assertNothingSent();
    });

    it('refuses a teacher or a student of acme and changes nothing', function (string $role) {
        $actor = User::factory()->for($this->acme, 'organization')->{$role}()->create();
        [$invitation] = resendableInvitation($this->acme);
        $before = invitationColumns($invitation);
        Notification::fake();

        $this->actingAs($actor)->post(resendUrl($this->acme, $invitation))->assertForbidden();

        expect(invitationColumns($invitation))->toBe($before);
        Notification::assertNothingSent();
    })->with(['teacher', 'student']);

    it('grants resend only to org admins of the invitation\'s organisation', function () {
        [$invitation] = resendableInvitation($this->acme);

        expect($this->admin->can('resend', $invitation))->toBeTrue()
            ->and($this->otherAdmin->can('resend', $invitation))->toBeTrue()
            ->and($this->betaAdmin->can('resend', $invitation))->toBeFalse()
            ->and($this->teacher->can('resend', $invitation))->toBeFalse();
    });
});

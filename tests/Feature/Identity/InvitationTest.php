<?php

use App\Modules\Identity\Contracts\IdentityProviderResolver;
use App\Modules\Identity\Database\Factories\InvitationFactory;
use App\Modules\Identity\Enums\InvitationStatus;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Events\InvitationAccepted;
use App\Modules\Identity\Events\InvitationCreated;
use App\Modules\Identity\Exceptions\IdentityProviderUnavailable;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\InvitationNotification;
use App\Modules\Tenancy\Support\OrganizationScope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-032 step 2 — an org admin invites a user by email with a role.
| UC-005 — the invitee accepts the invitation by setting name and password.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme', 'name' => 'Acme School']);
    $this->beta = $this->createOrganization(['subdomain' => 'beta']);

    $this->admin = User::factory()->for($this->acme, 'organization')->orgAdmin()->create();
});

function acceptUrl(Invitation $invitation, string $token): string
{
    return test()->tenantUrl($invitation->organization, "invitation/{$invitation->getKey()}/{$token}");
}

/**
 * @return array{0: Invitation, 1: string}
 */
function pendingInvitation(mixed $organization, array $attributes = [], ?Closure $factory = null): array
{
    $builder = Invitation::factory()->for($organization, 'organization');
    $builder = $factory ? $factory($builder) : $builder;
    $invitation = $builder->create($attributes);

    return [$invitation, (string) InvitationFactory::$lastToken];
}

function invitationRow(Invitation $invitation): Invitation
{
    return Invitation::query()->withoutGlobalScope(OrganizationScope::class)->findOrFail($invitation->getKey());
}

describe('inviting (UC-032)', function () {
    it('invites an email with a role, queues the invitation email and dispatches InvitationCreated', function () {
        Notification::fake();
        Event::fake([InvitationCreated::class]);

        $this->actingAs($this->admin)
            ->post($this->tenantUrl($this->acme, 'invitations'), ['email' => 'New.Teacher@Example.com', 'role' => 'teacher'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $invitation = $this->asTenant($this->acme, fn () => Invitation::query()->sole());

        expect($invitation->email)->toBe('new.teacher@example.com')
            ->and($invitation->role)->toBe(Role::Teacher)
            ->and($invitation->status)->toBe(InvitationStatus::Pending)
            ->and($invitation->invited_by)->toBe($this->admin->getKey())
            ->and($invitation->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()))->toBeTrue();

        Notification::assertSentTo(
            new AnonymousNotifiable,
            InvitationNotification::class,
            function (InvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($invitation) {
                return $notifiable->routes['mail'] === 'new.teacher@example.com'
                    && $notification->invitationId === $invitation->getKey()
                    && str_starts_with($notification->url, 'http://acme.uplift.test/invitation/'.$invitation->getKey().'/')
                    && $notification instanceof ShouldQueue;
            },
        );

        Event::assertDispatched(InvitationCreated::class, fn (InvitationCreated $event) => $event->invitationId === $invitation->getKey()
            && $event->organizationId === $this->acme->getKey()
            && $event->invitedBy === $this->admin->getKey());
    });

    it('sends the invitation email with the INT-001 retry policy', function () {
        $notification = new InvitationNotification('id', 'http://acme.uplift.test/x', 'Acme', 'teacher', 7);

        expect($notification->tries)->toBe(6)
            ->and($notification->timeout)->toBe(10)
            ->and($notification->backoff())->toBe([60, 300, 900, 3600, 21600]);

        $mail = $notification->toMail(new AnonymousNotifiable);
        expect($mail->actionUrl)->toBe('http://acme.uplift.test/x');
    });

    it('rejects an email already registered in another organisation', function () {
        Notification::fake();
        User::factory()->for($this->beta, 'organization')->create(['email' => 'taken@example.com']);

        $this->actingAs($this->admin)
            ->post($this->tenantUrl($this->acme, 'invitations'), ['email' => 'TAKEN@example.com', 'role' => 'student'])
            ->assertSessionHasErrors('email');

        expect(Invitation::query()->withoutGlobalScope(OrganizationScope::class)->count())->toBe(0);
        Notification::assertNothingSent();
    });

    it('rejects an email already registered in the same organisation', function () {
        Notification::fake();
        User::factory()->for($this->acme, 'organization')->create(['email' => 'member@example.com']);

        $this->actingAs($this->admin)
            ->post($this->tenantUrl($this->acme, 'invitations'), ['email' => 'member@example.com', 'role' => 'student'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    });

    it('rejects a second pending invitation for the same email', function () {
        Notification::fake();
        pendingInvitation($this->acme, ['email' => 'twice@example.com']);

        $this->actingAs($this->admin)
            ->post($this->tenantUrl($this->acme, 'invitations'), ['email' => 'twice@example.com', 'role' => 'student'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    });

    it('validates email and role', function () {
        $this->actingAs($this->admin)
            ->post($this->tenantUrl($this->acme, 'invitations'), ['email' => 'not-an-email', 'role' => 'platform_admin'])
            ->assertSessionHasErrors(['email', 'role']);
    });

    it('does not let students or teachers invite', function (string $role) {
        $actor = User::factory()->for($this->acme, 'organization')->state(['role' => Role::from($role)])->create();

        $this->actingAs($actor)
            ->post($this->tenantUrl($this->acme, 'invitations'), ['email' => 'x@example.com', 'role' => 'org_admin'])
            ->assertForbidden();

        expect(Invitation::query()->withoutGlobalScope(OrganizationScope::class)->count())->toBe(0);
    })->with(['student', 'teacher']);
});

describe('accepting (UC-005)', function () {
    it('shows the organisation and the assigned role', function () {
        [$invitation, $token] = pendingInvitation($this->acme, ['email' => 'invitee@example.com'], fn ($f) => $f->role(Role::Teacher));

        $this->get(acceptUrl($invitation, $token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/AcceptInvitation')
                ->where('invitation.organization', 'Acme School')
                ->where('invitation.role', 'teacher')
                ->where('invitation.email', 'invitee@example.com'));
    });

    it('activates a teacher invitation as a verified teacher of the inviting organisation only, and logs them in', function () {
        Event::fake([InvitationAccepted::class]);
        [$invitation, $token] = pendingInvitation($this->acme, ['email' => 'invitee@example.com'], fn ($f) => $f->role(Role::Teacher));

        $this->post(acceptUrl($invitation, $token), [
            'name' => 'Ada Lovelace',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
        ])->assertSessionHasNoErrors()->assertRedirect($this->tenantUrl($this->acme, 'dashboard'));

        $users = User::query()->withoutGlobalScope(OrganizationScope::class)->where('email', 'invitee@example.com')->get();

        expect($users)->toHaveCount(1);
        $user = $users->first();

        expect($user->role)->toBe(Role::Teacher)
            ->and($user->organization_id)->toBe($this->acme->getKey())
            ->and($user->name)->toBe('Ada Lovelace')
            ->and($user->is_active)->toBeTrue()
            ->and($user->email_verified_at)->not->toBeNull()
            ->and(Hash::check('a-long-enough-password', (string) $user->password))->toBeTrue()
            ->and(invitationRow($invitation)->status)->toBe(InvitationStatus::Accepted);

        $this->asTenant($this->beta, fn () => expect(User::query()->where('email', 'invitee@example.com')->exists())->toBeFalse());

        $this->assertAuthenticatedAs($user);
        Event::assertDispatched(InvitationAccepted::class, fn (InvitationAccepted $event) => $event->userId === $user->getKey());
    });

    it('rejects an invitation sent 8 days ago as expired', function () {
        [$invitation, $token] = pendingInvitation($this->acme, [], fn ($f) => $f->sentDaysAgo(8));

        $this->get(acceptUrl($invitation, $token))
            ->assertStatus(410)
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/InvitationUnavailable')
                ->where('reason', 'expired'));

        expect(invitationRow($invitation)->status)->toBe(InvitationStatus::Expired);

        $this->post(acceptUrl($invitation, $token), [
            'name' => 'Late',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
        ])->assertStatus(410);

        expect(User::query()->withoutGlobalScope(OrganizationScope::class)->where('email', $invitation->email)->exists())->toBeFalse();
        $this->assertGuest();
    });

    it('accepts an invitation sent 6 days ago', function () {
        [$invitation, $token] = pendingInvitation($this->acme, [], fn ($f) => $f->sentDaysAgo(6));

        $this->get(acceptUrl($invitation, $token))->assertOk();
    });

    it('rejects the link of an accepted invitation when it is opened again', function () {
        [$invitation, $token] = pendingInvitation($this->acme);
        $form = ['name' => 'First', 'password' => 'a-long-enough-password', 'password_confirmation' => 'a-long-enough-password'];

        $this->post(acceptUrl($invitation, $token), $form)->assertRedirect();
        auth()->guard('web')->logout();

        $this->get(acceptUrl($invitation, $token))
            ->assertStatus(410)
            ->assertInertia(fn (Assert $page) => $page->component('auth/InvitationUnavailable')->where('reason', 'accepted'));

        $this->post(acceptUrl($invitation, $token), [...$form, 'name' => 'Second'])->assertStatus(410);

        expect(User::query()->withoutGlobalScope(OrganizationScope::class)->where('email', $invitation->email)->pluck('name')->all())
            ->toBe(['First']);
    });

    it('rejects a revoked invitation', function () {
        [$invitation, $token] = pendingInvitation($this->acme, [], fn ($f) => $f->inStatus(InvitationStatus::Revoked));

        $this->get(acceptUrl($invitation, $token))
            ->assertStatus(410)
            ->assertInertia(fn (Assert $page) => $page->component('auth/InvitationUnavailable')->where('reason', 'revoked'));
    });

    it('does not find an invitation with a wrong token', function () {
        [$invitation] = pendingInvitation($this->acme);

        $this->get(acceptUrl($invitation, 'wrong-token'))->assertNotFound();
        $this->post(acceptUrl($invitation, 'wrong-token'), [
            'name' => 'X', 'password' => 'a-long-enough-password', 'password_confirmation' => 'a-long-enough-password',
        ])->assertNotFound();

        expect(invitationRow($invitation)->status)->toBe(InvitationStatus::Pending);
    });

    it('does not find an invitation of acme on beta\'s subdomain', function () {
        [$invitation, $token] = pendingInvitation($this->acme);

        $this->get($this->tenantUrl($this->beta, "invitation/{$invitation->getKey()}/{$token}"))->assertNotFound();
    });

    it('rejects acceptance when the email was registered elsewhere in the meantime', function () {
        [$invitation, $token] = pendingInvitation($this->acme, ['email' => 'race@example.com']);
        User::factory()->for($this->beta, 'organization')->create(['email' => 'race@example.com']);

        $this->post(acceptUrl($invitation, $token), [
            'name' => 'X', 'password' => 'a-long-enough-password', 'password_confirmation' => 'a-long-enough-password',
        ])->assertSessionHasErrors('invitation');

        expect(invitationRow($invitation)->status)->toBe(InvitationStatus::Pending);
        $this->assertGuest();
    });

    it('requires a password of at least 8 characters', function () {
        [$invitation, $token] = pendingInvitation($this->acme);

        $this->post(acceptUrl($invitation, $token), ['name' => 'X', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');

        expect(invitationRow($invitation)->status)->toBe(InvitationStatus::Pending);
    });

    it('lets an invited user log in with email and password while identity providers are unavailable (INT-002)', function () {
        $this->app->bind(IdentityProviderResolver::class, fn () => new class implements IdentityProviderResolver
        {
            public function providersFor(string $organizationId): array
            {
                throw IdentityProviderUnavailable::because('test');
            }

            public function resolveUser(string $organizationId, string $provider, string $externalSubject): ?User
            {
                throw IdentityProviderUnavailable::because($provider);
            }
        });

        [$invitation, $token] = pendingInvitation($this->acme, ['email' => 'later@example.com']);

        $this->get($this->tenantUrl($this->acme, 'login'))
            ->assertInertia(fn (Assert $page) => $page->where('identityProvidersUnavailable', true));

        $this->post(acceptUrl($invitation, $token), [
            'name' => 'Later', 'password' => 'a-long-enough-password', 'password_confirmation' => 'a-long-enough-password',
        ])->assertRedirect();
        auth()->guard('web')->logout();

        $this->post($this->tenantUrl($this->acme, 'login'), ['email' => 'later@example.com', 'password' => 'a-long-enough-password'])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    });
});

it('writes neither the invited email nor the token to logs', function () {
    $logged = [];
    Log::listen(function ($message) use (&$logged) {
        $logged[] = $message->message.' '.json_encode($message->context);
    });
    Notification::fake();

    $this->actingAs($this->admin)
        ->post($this->tenantUrl($this->acme, 'invitations'), ['email' => 'private@example.com', 'role' => 'student']);
    auth()->guard('web')->logout();

    [$invitation, $token] = pendingInvitation($this->acme, ['email' => 'other.private@example.com']);
    $this->post(acceptUrl($invitation, $token), [
        'name' => 'Private Person', 'password' => 'a-long-enough-password', 'password_confirmation' => 'a-long-enough-password',
    ]);

    expect(User::query()->withoutGlobalScope(OrganizationScope::class)->where('email', 'other.private@example.com')->exists())->toBeTrue();

    foreach ($logged as $line) {
        expect($line)->not->toContain('private@example.com')
            ->not->toContain('other.private@example.com')
            ->not->toContain($token)
            ->not->toContain('Private Person');
    }
});

describe('revoking (UC-032, OWN-002)', function () {
    it('revokes a pending invitation of the admin\'s organisation', function () {
        [$invitation] = pendingInvitation($this->acme);

        $this->actingAs($this->admin)
            ->post($this->tenantUrl($this->acme, "invitations/{$invitation->getKey()}/revoke"))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect(invitationRow($invitation)->status)->toBe(InvitationStatus::Revoked);
    });

    it('rejects revoking an invitation that is no longer pending and leaves it unchanged', function () {
        [$invitation] = pendingInvitation($this->acme, [], fn ($f) => $f->inStatus(InvitationStatus::Accepted));

        $this->actingAs($this->admin)
            ->post($this->tenantUrl($this->acme, "invitations/{$invitation->getKey()}/revoke"))
            ->assertSessionHasErrors('invitation');

        expect(invitationRow($invitation)->status)->toBe(InvitationStatus::Accepted);
    });
});

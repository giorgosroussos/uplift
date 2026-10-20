<?php

use App\Modules\Identity\Database\Factories\UserFactory;
use App\Modules\Identity\Models\PasswordResetToken;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-004 — Recover a forgotten password with a single-use link.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->beta = $this->createOrganization(['subdomain' => 'beta']);

    $this->user = User::factory()->for($this->acme, 'organization')->create(['email' => 'student@acme.test']);
});

/**
 * Requests a reset link on acme and returns the token mailed to the user.
 */
function requestResetToken(object $test): string
{
    Notification::fake();

    $test->post('http://acme.uplift.test/forgot-password', ['email' => 'student@acme.test']);

    $token = null;
    Notification::assertSentTo($test->user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token): bool {
        parse_str((string) parse_url($notification->url, PHP_URL_QUERY), $query);
        $token = basename((string) parse_url($notification->url, PHP_URL_PATH));

        return $query['email'] === 'student@acme.test';
    });

    return $token;
}

/**
 * @return array<string, string>
 */
function resetPayload(string $token, string $password = 'a-brand-new-secret'): array
{
    return [
        'token' => $token,
        'email' => 'student@acme.test',
        'password' => $password,
        'password_confirmation' => $password,
    ];
}

it('shows the forgot password page on the organisation subdomain only', function () {
    $this->get($this->tenantUrl($this->acme, 'forgot-password'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/ForgotPassword'));

    $this->get($this->apexUrl('forgot-password'))->assertNotFound();
});

it('mails a reset link on the organisation subdomain from a queued notification', function () {
    $token = requestResetToken($this);

    Notification::assertSentTo($this->user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($token): bool {
        expect($notification)->toBeInstanceOf(ShouldQueue::class)
            ->and($notification->url)->toStartWith($this->tenantUrl($this->acme, "reset-password/{$token}"));

        return true;
    });
});

it('queues the reset mail instead of sending it during the request', function () {
    Queue::fake();

    $this->post($this->tenantUrl($this->acme, 'forgot-password'), ['email' => 'student@acme.test']);

    Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof ResetPasswordNotification);
});

it('renders the reset mail in the user\'s language with the organisation link', function () {
    $this->user->forceFill(['locale' => 'el'])->save();
    $notification = new ResetPasswordNotification($this->tenantUrl($this->acme, 'reset-password/abc'));

    $mail = (string) $notification->toMail($this->user)->render();

    expect($this->user->preferredLocale())->toBe('el');
    $this->app->setLocale('el');
    expect($notification->toMail($this->user)->subject)->toBe(__('notifications.reset_password.subject'))
        ->and($mail)->toContain('acme.uplift.test/reset-password/abc');
});

it('resets the password with a valid link and logs in with the new one', function () {
    $token = requestResetToken($this);

    $this->post($this->tenantUrl($this->acme, 'reset-password'), resetPayload($token))
        ->assertSessionHasNoErrors()
        ->assertRedirect($this->tenantUrl($this->acme, 'login'));

    expect(Hash::check('a-brand-new-secret', $this->user->fresh()->password))->toBeTrue();
});

it('rejects a reset link issued 61 minutes ago', function () {
    $token = requestResetToken($this);

    $this->travel(61)->minutes();

    $this->post($this->tenantUrl($this->acme, 'reset-password'), resetPayload($token))
        ->assertSessionHasErrors(['email' => __('passwords.token')]);

    expect(Hash::check(UserFactory::PASSWORD, $this->user->fresh()->password))->toBeTrue();
});

it('accepts a reset link issued 59 minutes ago', function () {
    $token = requestResetToken($this);

    $this->travel(59)->minutes();

    $this->post($this->tenantUrl($this->acme, 'reset-password'), resetPayload($token))
        ->assertSessionHasNoErrors();
});

it('rejects a reset link already used once', function () {
    $token = requestResetToken($this);

    $this->post($this->tenantUrl($this->acme, 'reset-password'), resetPayload($token))->assertSessionHasNoErrors();

    $this->post($this->tenantUrl($this->acme, 'reset-password'), resetPayload($token, 'yet-another-secret'))
        ->assertSessionHasErrors(['email' => __('passwords.token')]);

    expect(Hash::check('a-brand-new-secret', $this->user->fresh()->password))->toBeTrue();
});

it('rejects acme\'s reset link on beta\'s subdomain', function () {
    User::factory()->for($this->beta, 'organization')->create(['email' => 'student@acme.test']);
    $token = requestResetToken($this);

    $this->post($this->tenantUrl($this->beta, 'reset-password'), resetPayload($token))
        ->assertSessionHasErrors('email');

    expect(Hash::check(UserFactory::PASSWORD, $this->user->fresh()->password))->toBeTrue();
});

it('answers an unregistered email exactly like a registered one and sends no email', function () {
    // A user of another organisation is unknown on acme as well.
    User::factory()->for($this->beta, 'organization')->create(['email' => 'only-beta@beta.test']);

    Notification::fake();
    $form = $this->tenantUrl($this->acme, 'forgot-password');

    $registered = $this->from($form)->post($form, ['email' => 'student@acme.test']);
    $registeredStatus = session('status');
    Notification::assertSentTo($this->user, ResetPasswordNotification::class);

    Notification::fake();
    $unknown = $this->from($form)->post($form, ['email' => 'nobody@acme.test']);
    $unknownStatus = session('status');

    $otherOrganisation = $this->from($form)->post($form, ['email' => 'only-beta@beta.test']);
    $otherOrganisationStatus = session('status');

    Notification::assertNothingSent();

    foreach ([[$unknown, $unknownStatus], [$otherOrganisation, $otherOrganisationStatus]] as [$response, $status]) {
        expect($response->getStatusCode())->toBe($registered->getStatusCode())
            ->and($response->headers->get('Location'))->toBe($registered->headers->get('Location'))
            ->and($status)->toBe($registeredStatus);
        $response->assertSessionHasNoErrors();
    }

    expect($registeredStatus)->toBe(__('passwords.sent'));
});

it('answers a repeated request within the throttle exactly like the first one', function () {
    Notification::fake();
    $form = $this->tenantUrl($this->acme, 'forgot-password');

    $first = $this->from($form)->post($form, ['email' => 'student@acme.test']);
    $second = $this->from($form)->post($form, ['email' => 'student@acme.test']);

    $second->assertSessionHasNoErrors()->assertSessionHas('status', __('passwords.sent'));
    expect($second->headers->get('Location'))->toBe($first->headers->get('Location'));
    Notification::assertSentToTimes($this->user, ResetPasswordNotification::class, 1);
});

it('requires a new password of at least 8 characters that is not known to be compromised', function (string $password) {
    $token = requestResetToken($this);

    $this->post($this->tenantUrl($this->acme, 'reset-password'), resetPayload($token, $password))
        ->assertSessionHasErrors('password');

    expect(Hash::check(UserFactory::PASSWORD, $this->user->fresh()->password))->toBeTrue();
})->with([
    'too short' => ['short7!'],
    'compromised' => ['password1'],
]);

it('ends the user\'s other sessions when the password changes', function () {
    $this->actingAs($this->user)->get($this->tenantUrl($this->acme, 'dashboard'))->assertOk();

    // The password is changed elsewhere (e.g. a reset from another device).
    $this->user->forceFill(['password' => 'changed-elsewhere'])->save();

    $this->get($this->tenantUrl($this->acme, 'dashboard'))->assertRedirect();
    $this->assertGuest();
});

it('keeps reset tokens per organisation', function () {
    $this->assertTenantIsolated(PasswordResetToken::class, fn () => PasswordResetToken::query()->create([
        'email' => fake()->unique()->safeEmail(),
        'token' => Hash::make('token'),
    ]));
});

it('issues no reset token without an organisation', function () {
    expect(Password::broker()->getRepository()->exists($this->user, 'anything'))->toBeFalse();
});

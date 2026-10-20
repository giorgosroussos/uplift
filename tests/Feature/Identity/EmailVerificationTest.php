<?php

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-004 — Verify the email address.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->user = User::factory()->for($this->acme, 'organization')->teacher()->unverified()->create();
});

it('sends an unverified user from any page other than verification to the verification notice', function (string $path) {
    $this->actingAs($this->user)
        ->get($this->tenantUrl($this->acme, $path))
        ->assertRedirect($this->tenantUrl($this->acme, 'email/verify'));
})->with([
    'dashboard' => ['dashboard'],
]);

it('shows the verification notice to an unverified user', function () {
    $this->actingAs($this->user)
        ->get($this->tenantUrl($this->acme, 'email/verify'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/VerifyEmail'));
});

it('mails a verification link on the organisation subdomain from a queued notification', function () {
    Notification::fake();

    $this->actingAs($this->user)
        ->post($this->tenantUrl($this->acme, 'email/verification-notification'))
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($this->user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification): bool {
        expect($notification)->toBeInstanceOf(ShouldQueue::class)
            ->and($notification->url)->toStartWith($this->tenantUrl($this->acme, "email/verify/{$this->user->id}/"));

        return true;
    });
});

it('builds the verification link on the organisation subdomain even outside its requests', function () {
    Notification::fake();

    // E.g. a mail requested on the apex domain or in a worker.
    $this->user->sendEmailVerificationNotification();

    Notification::assertSentTo($this->user, VerifyEmailNotification::class, fn (VerifyEmailNotification $notification): bool => str_starts_with($notification->url, 'http://acme.uplift.test/email/verify/'));
});

it('verifies the email with the mailed link and then lets the user in', function () {
    Notification::fake();
    Event::fake([Verified::class]);

    $this->user->sendEmailVerificationNotification();

    $url = null;
    Notification::assertSentTo($this->user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use (&$url): bool {
        $url = $notification->url;

        return true;
    });

    $this->actingAs($this->user)->get($url)
        ->assertRedirect($this->tenantUrl($this->acme, 'dashboard?verified=1'));

    Event::assertDispatched(Verified::class);
    expect($this->user->fresh()->hasVerifiedEmail())->toBeTrue();

    $this->get($this->tenantUrl($this->acme, 'dashboard'))->assertOk();
});

it('rejects a tampered verification link', function () {
    $this->actingAs($this->user)
        ->get($this->tenantUrl($this->acme, "email/verify/{$this->user->id}/".sha1('other@example.com')))
        ->assertForbidden();

    expect($this->user->fresh()->hasVerifiedEmail())->toBeFalse();
});

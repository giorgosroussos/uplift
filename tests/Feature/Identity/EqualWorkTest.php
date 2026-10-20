<?php

use App\Modules\Identity\Database\Factories\UserFactory;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Enums\OrganizationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Support\CountingHasher;

/*
| UC-003 / UC-004: response time gives no hint whether an email has an
| account. Every login attempt pays exactly one password check, and every
| reset-link request pays exactly one token hash, whatever the outcome.
| (Counting hasher calls keeps the test deterministic.)
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->beta = $this->createOrganization(['subdomain' => 'beta']);

    $acmeUsers = User::factory()->for($this->acme, 'organization');
    $acmeUsers->teacher()->create(['email' => 'teacher@acme.test']);
    $acmeUsers->withoutPassword()->create(['email' => 'sso@acme.test']);
    $acmeUsers->inactive()->create(['email' => 'inactive@acme.test']);
    User::factory()->for($this->beta, 'organization')->create(['email' => 'only-beta@beta.test']);

    Notification::fake();
});

/**
 * Counts the hasher calls of everything resolved from here on.
 */
function countHashing(): CountingHasher
{
    $hasher = new CountingHasher(app('hash'));
    Hash::swap($hasher);
    app()->instance('hash.driver', $hasher); // the Hasher contract
    // Password brokers hold the hasher they were built with: build new ones.
    app()->forgetInstance('auth.password');
    Password::clearResolvedInstance('auth.password');

    return $hasher;
}

it('checks a password exactly once on every login path', function (string $email, string $password, bool $succeeds) {
    $hasher = countHashing();

    $this->post($this->tenantUrl($this->acme, 'login'), ['email' => $email, 'password' => $password]);

    $succeeds ? $this->assertAuthenticated() : $this->assertGuest();
    expect($hasher->checks)->toBe(1);
})->with([
    'correct credentials' => ['teacher@acme.test', UserFactory::PASSWORD, true],
    'wrong password' => ['teacher@acme.test', 'wrong-password', false],
    'unknown email' => ['nobody@acme.test', UserFactory::PASSWORD, false],
    'user of another organisation' => ['only-beta@beta.test', UserFactory::PASSWORD, false],
    'user without a password' => ['sso@acme.test', UserFactory::PASSWORD, false],
    'deactivated user' => ['inactive@acme.test', UserFactory::PASSWORD, false],
]);

it('checks a password exactly once for a user of a suspended organisation', function () {
    $this->acme->transitionTo(OrganizationStatus::Suspended);
    $hasher = countHashing();

    $this->post($this->tenantUrl($this->acme, 'login'), ['email' => 'teacher@acme.test', 'password' => UserFactory::PASSWORD]);

    $this->assertGuest();
    expect($hasher->checks)->toBe(1);
});

it('hashes exactly once on every reset-link path', function (string $email) {
    $hasher = countHashing();

    $this->post($this->tenantUrl($this->acme, 'forgot-password'), ['email' => $email])
        ->assertSessionHas('status', __('passwords.sent'));

    expect($hasher->makes)->toBe(1);
})->with([
    'registered email' => ['teacher@acme.test'],
    'unknown email' => ['nobody@acme.test'],
    'user of another organisation' => ['only-beta@beta.test'],
]);

it('hashes exactly once for a reset-link request within the throttle', function () {
    $this->post($this->tenantUrl($this->acme, 'forgot-password'), ['email' => 'teacher@acme.test']);

    $hasher = countHashing();

    $this->post($this->tenantUrl($this->acme, 'forgot-password'), ['email' => 'teacher@acme.test'])
        ->assertSessionHas('status', __('passwords.sent'));

    expect($hasher->makes)->toBe(1);
});

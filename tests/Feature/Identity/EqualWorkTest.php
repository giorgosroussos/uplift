<?php

use App\Modules\Identity\Database\Factories\UserFactory;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\EqualWork;
use App\Modules\Tenancy\Enums\OrganizationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Support\CountingHasher;

/*
| UC-003 / UC-004: response time gives no hint whether an email has an
| account. Every login attempt and every reset submit pays exactly one hash
| check and no hash make; every reset-link request pays exactly one hash make,
| whatever the outcome. (Counting hasher calls keeps the test deterministic.)
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);
    EqualWork::flush();

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->beta = $this->createOrganization(['subdomain' => 'beta']);

    $acmeUsers = User::factory()->for($this->acme, 'organization');
    $acmeUsers->teacher()->create(['email' => 'teacher@acme.test']);
    $acmeUsers->withoutPassword()->create(['email' => 'sso@acme.test']);
    $acmeUsers->inactive()->create(['email' => 'inactive@acme.test']);
    User::factory()->for($this->beta, 'organization')->create(['email' => 'only-beta@beta.test']);

    Notification::fake();
});

afterEach(function () {
    EqualWork::flush();
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

/**
 * The dummy hash exists in the cache (warm-up), but this "request" starts
 * without it in memory, like every PHP-FPM request.
 */
function warmDummyHash(): void
{
    EqualWork::check(app('hash'), 'warm-up');
    EqualWork::flush(keepCache: true);
}

it('makes the dummy hash once and reuses it from the cache in later requests', function () {
    $hasher = countHashing();

    EqualWork::check($hasher, 'first');
    expect($hasher->makes)->toBe(1)->and($hasher->checks)->toBe(1);

    EqualWork::flush(keepCache: true); // a new request
    EqualWork::check($hasher, 'second');
    expect($hasher->makes)->toBe(1)->and($hasher->checks)->toBe(2);
});

it('makes a new dummy hash when the hashing cost changes', function () {
    $hasher = countHashing();
    EqualWork::check($hasher, 'first');

    EqualWork::flush(keepCache: true);
    config(['hashing.argon.time' => 2]);
    EqualWork::check($hasher, 'second');

    expect($hasher->makes)->toBe(2);
});

it('checks a password exactly once and makes no hash on every login path', function (string $email, string $password, bool $succeeds) {
    warmDummyHash();
    $hasher = countHashing();

    $this->post($this->tenantUrl($this->acme, 'login'), ['email' => $email, 'password' => $password]);

    $succeeds ? $this->assertAuthenticated() : $this->assertGuest();
    expect($hasher->checks)->toBe(1)->and($hasher->makes)->toBe(0);
})->with([
    'correct credentials' => ['teacher@acme.test', UserFactory::PASSWORD, true],
    'wrong password' => ['teacher@acme.test', 'wrong-password', false],
    'unknown email' => ['nobody@acme.test', UserFactory::PASSWORD, false],
    'user of another organisation' => ['only-beta@beta.test', UserFactory::PASSWORD, false],
    'user without a password' => ['sso@acme.test', UserFactory::PASSWORD, false],
    'deactivated user' => ['inactive@acme.test', UserFactory::PASSWORD, false],
]);

it('checks a password exactly once and makes no hash for a user of a suspended organisation', function () {
    $this->acme->transitionTo(OrganizationStatus::Suspended);
    warmDummyHash();
    $hasher = countHashing();

    $this->post($this->tenantUrl($this->acme, 'login'), ['email' => 'teacher@acme.test', 'password' => UserFactory::PASSWORD]);

    $this->assertGuest();
    expect($hasher->checks)->toBe(1)->and($hasher->makes)->toBe(0);
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

it('answers a bogus reset identically and with equal work for unknown and registered emails', function () {
    // inactive@ has a pending reset; teacher@ has none.
    $this->post($this->tenantUrl($this->acme, 'forgot-password'), ['email' => 'inactive@acme.test']);

    $form = $this->tenantUrl($this->acme, 'reset-password/bogus-token');
    $outcomes = [];

    foreach (['nobody@acme.test', 'inactive@acme.test', 'teacher@acme.test'] as $email) {
        warmDummyHash();
        $hasher = countHashing();

        $response = $this->from($form)->post($this->tenantUrl($this->acme, 'reset-password'), [
            'token' => 'bogus-token',
            'email' => $email,
            'password' => 'a-brand-new-secret',
            'password_confirmation' => 'a-brand-new-secret',
        ]);

        $errors = session('errors');
        $outcomes[$email] = [
            'status' => $response->getStatusCode(),
            'location' => $response->headers->get('Location'),
            'errors' => $errors['default']['messages'] ?? $errors?->getBag('default')->toArray(),
            'checks' => $hasher->checks,
            'makes' => $hasher->makes,
        ];
    }

    $expected = [
        'status' => 302,
        'location' => $form,
        'errors' => ['email' => [__('passwords.token')]],
        'checks' => 1,
        'makes' => 0,
    ];

    expect($outcomes)->toBe(array_fill_keys(array_keys($outcomes), $expected));
});

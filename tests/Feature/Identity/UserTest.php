<?php

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Enums\Locale;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/*
| ENT-User: one organisation and one role per user (SEC-004).
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->acme = $this->createOrganization(['subdomain' => 'acme', 'default_locale' => Locale::Greek]);
    $this->beta = $this->createOrganization(['subdomain' => 'beta']);
});

it('isolates users per organisation', function () {
    $this->assertTenantIsolated(User::class, fn () => User::factory()->create());
});

it('belongs to exactly one organisation with exactly one role', function () {
    $user = User::factory()->for($this->acme, 'organization')->teacher()->create();

    expect($user->organization_id)->toBe($this->acme->id)
        ->and($user->role)->toBe(Role::Teacher)
        ->and($user->is_active)->toBeTrue();

    expect(fn () => User::factory()->for($this->acme, 'organization')->create(['role' => null]))
        ->toThrow(QueryException::class);
});

it('rejects an unknown role', function () {
    expect(fn () => User::query()->withoutGlobalScopes()->getConnection()->table('users')->insert([
        'id' => (string) Str::uuid7(),
        'organization_id' => $this->acme->id,
        'role' => 'platform_admin',
        'name' => 'X',
        'nickname' => 'x',
        'email' => 'x@example.com',
        'is_active' => true,
    ]))->toThrow(QueryException::class);
});

it('uses UUID primary keys', function () {
    $user = User::factory()->for($this->acme, 'organization')->create();

    expect(Str::isUuid($user->id))->toBeTrue();
});

it('generates a nickname, unique within the organisation, unless one is given', function () {
    $generated = User::factory()->for($this->acme, 'organization')->create();
    $chosen = User::factory()->for($this->acme, 'organization')->create(['nickname' => 'quizmaster']);

    expect($generated->nickname)->toMatch('/^user-\d{6}$/')
        ->and($chosen->nickname)->toBe('quizmaster');

    // The same nickname in another organisation is fine; in the same one it is not.
    User::factory()->for($this->beta, 'organization')->create(['nickname' => 'quizmaster']);

    expect(fn () => User::factory()->for($this->acme, 'organization')->create(['nickname' => 'quizmaster']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('stores emails in lower case and keeps them unique within the organisation only', function () {
    $user = User::factory()->for($this->acme, 'organization')->create(['email' => ' Someone@Example.COM ']);

    expect($user->email)->toBe('someone@example.com');

    User::factory()->for($this->beta, 'organization')->create(['email' => 'someone@example.com']);

    expect(fn () => User::factory()->for($this->acme, 'organization')->create(['email' => 'SOMEONE@example.com']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows a user without a password', function () {
    $user = User::factory()->for($this->acme, 'organization')->withoutPassword()->create();

    expect($user->fresh()->password)->toBeNull();
});

it('stores a date of birth and a minor flag', function () {
    $user = User::factory()->for($this->acme, 'organization')->create([
        'date_of_birth' => '2012-03-04',
        'is_minor' => true,
    ])->fresh();

    expect($user->date_of_birth->toDateString())->toBe('2012-03-04')
        ->and($user->is_minor)->toBeTrue();
});

it('prefers the user\'s locale and falls back to the organisation\'s', function () {
    $withLocale = User::factory()->for($this->acme, 'organization')->create(['locale' => Locale::English]);
    $withoutLocale = User::factory()->for($this->acme, 'organization')->create();

    expect($withLocale->preferredLocale())->toBe('en')
        ->and($withoutLocale->preferredLocale())->toBe('el');
});

it('hides the password hash and remember token when serialised', function () {
    $user = User::factory()->for($this->acme, 'organization')->create();

    expect($user->toArray())->not->toHaveKeys(['password', 'remember_token']);
});

it('stores timestamps in UTC', function () {
    $user = User::factory()->for($this->acme, 'organization')->create();

    expect(config('app.timezone'))->toBe('UTC')
        ->and($user->created_at->getTimezone()->getName())->toBe('UTC');
});

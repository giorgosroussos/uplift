<?php

use App\Modules\Platform\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/*
| P8-T1 — platform admins are created by an operator on the console, with a
| password of at least 8 characters that is not known to be compromised
| (SEC-010); MFA is set up at the first login (SEC-011).
*/

uses(RefreshDatabase::class);

it('creates a platform admin without MFA, with an argon2id password', function () {
    $this->artisan('platform:create-admin')
        ->expectsQuestion('Name', 'Root Admin')
        ->expectsQuestion('Email', 'Root@Uplift.Test')
        ->expectsQuestion('Password', 'a-long-platform-password')
        ->expectsQuestion('Repeat the password', 'a-long-platform-password')
        ->doesntExpectOutputToContain('root@uplift.test')
        ->assertSuccessful();

    $admin = PlatformAdmin::query()->sole();

    expect($admin->email)->toBe('root@uplift.test')
        ->and($admin->name)->toBe('Root Admin')
        ->and($admin->password)->toStartWith('$argon2id$')
        ->and(Hash::check('a-long-platform-password', $admin->password))->toBeTrue()
        ->and($admin->hasConfirmedTwoFactor())->toBeFalse()
        ->and(DB::table('users')->count())->toBe(0);
});

it('refuses a short password', function () {
    $this->artisan('platform:create-admin')
        ->expectsQuestion('Name', 'Root Admin')
        ->expectsQuestion('Email', 'root@uplift.test')
        ->expectsQuestion('Password', 'short')
        ->expectsQuestion('Repeat the password', 'short')
        ->assertFailed();

    expect(PlatformAdmin::query()->count())->toBe(0);
});

it('refuses a compromised password', function () {
    $this->artisan('platform:create-admin')
        ->expectsQuestion('Name', 'Root Admin')
        ->expectsQuestion('Email', 'root@uplift.test')
        ->expectsQuestion('Password', 'password123')
        ->expectsQuestion('Repeat the password', 'password123')
        ->assertFailed();

    expect(PlatformAdmin::query()->count())->toBe(0);
});

it('refuses passwords that do not match', function () {
    $this->artisan('platform:create-admin')
        ->expectsQuestion('Name', 'Root Admin')
        ->expectsQuestion('Email', 'root@uplift.test')
        ->expectsQuestion('Password', 'a-long-platform-password')
        ->expectsQuestion('Repeat the password', 'another-long-password')
        ->assertFailed();

    expect(PlatformAdmin::query()->count())->toBe(0);
});

it('refuses an email that already has a platform admin, whatever its case', function () {
    PlatformAdmin::factory()->create(['email' => 'root@uplift.test']);

    $this->artisan('platform:create-admin')
        ->expectsQuestion('Name', 'Second')
        ->expectsQuestion('Email', 'ROOT@uplift.test')
        ->expectsQuestion('Password', 'a-long-platform-password')
        ->expectsQuestion('Repeat the password', 'a-long-platform-password')
        ->doesntExpectOutputToContain('root@uplift.test')
        ->assertFailed();

    expect(PlatformAdmin::query()->count())->toBe(1);
});

it('refuses an invalid email', function () {
    $this->artisan('platform:create-admin')
        ->expectsQuestion('Name', 'Root Admin')
        ->expectsQuestion('Email', 'not-an-email')
        ->expectsQuestion('Password', 'a-long-platform-password')
        ->expectsQuestion('Repeat the password', 'a-long-platform-password')
        ->assertFailed();

    expect(PlatformAdmin::query()->count())->toBe(0);
});

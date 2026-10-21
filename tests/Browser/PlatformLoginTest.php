<?php

use App\Modules\Platform\Database\Factories\PlatformAdminFactory;
use App\Modules\Platform\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

/*
| Browser flow of P8-T1: a platform admin logs in on the apex domain with the
| password, then sets up the required TOTP MFA (first login) or passes the
| TOTP challenge (SEC-006, SEC-011).
|
| The browser test server answers as http://127.0.0.1:<port>: 127.0.0.1 is
| the apex domain here.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => '127.0.0.1', 'app.locale' => 'en']);
});

it('sets up MFA at the first login and shows the recovery codes once', function () {
    $admin = PlatformAdmin::factory()->create(['email' => 'root@uplift.test', 'name' => 'Root Admin']);

    $page = visit('/admin/login')
        ->assertSee('Platform administration')
        ->fill('email', 'root@uplift.test')
        ->fill('password', PlatformAdminFactory::PASSWORD)
        ->click('@platform-login-button')
        ->assertSee('Set up two-factor authentication')
        ->assertVisible('@platform-setup-qr');

    $secret = Fortify::currentEncrypter()->decrypt((string) $admin->fresh()->two_factor_secret);

    $page->assertSee($secret)
        ->type('#code', (new Google2FA)->getCurrentOtp($secret))
        ->click('@platform-setup-submit')
        ->assertSee('Your recovery codes')
        ->assertSee($admin->fresh()->recoveryCodes()[0])
        ->click('@platform-recovery-continue')
        ->assertSee('Signed in as Root Admin')
        ->assertNoJavaScriptErrors();

    expect($admin->fresh()->hasConfirmedTwoFactor())->toBeTrue();
});

it('asks an admin with MFA for a code, then lets them log out', function () {
    $secret = (new Google2FA)->generateSecretKey();
    PlatformAdmin::factory()->withTwoFactor($secret)->create(['email' => 'root@uplift.test', 'name' => 'Root Admin']);

    visit('/admin/login')
        ->fill('email', 'root@uplift.test')
        ->fill('password', PlatformAdminFactory::PASSWORD)
        ->click('@platform-login-button')
        ->assertSee('Two-factor authentication')
        ->type('#code', '000000')
        ->click('@platform-challenge-submit')
        ->assertSee('The authentication code is not valid.')
        ->type('#code', (new Google2FA)->getCurrentOtp($secret))
        ->click('@platform-challenge-submit')
        ->assertSee('Signed in as Root Admin')
        ->click('@platform-logout')
        ->assertSee('Log in with your platform admin account')
        ->assertNoJavaScriptErrors();
});

it('rejects wrong credentials on the platform login', function () {
    PlatformAdmin::factory()->create(['email' => 'root@uplift.test']);

    visit('/admin/login')
        ->fill('email', 'root@uplift.test')
        ->fill('password', 'not-the-password')
        ->click('@platform-login-button')
        ->assertSee('These credentials do not match our records.')
        ->assertNoJavaScriptErrors();
});

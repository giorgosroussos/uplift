<?php

use App\Modules\Identity\Database\Factories\UserFactory;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;

/*
| UC-006 step 3 — optional TOTP MFA for org admins (SEC-011, SEC-012):
| only org admins enable or disable it, enabling needs the password and a
| first valid code, the login challenge is limited to 5 attempts per minute
| per login session, and demotion clears it.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);

    $acmeUsers = User::factory()->for($this->acme, 'organization');
    $this->admin = $acmeUsers->orgAdmin()->create(['email' => 'alice@acme.test']);
    $this->otherAdmin = $acmeUsers->orgAdmin()->create(['email' => 'olga@acme.test']);
    $this->teacher = $acmeUsers->teacher()->create(['email' => 'tom@acme.test']);
    $this->student = $acmeUsers->student()->create(['email' => 'sam@acme.test']);

    $this->google2fa = new Google2FA;
});

/**
 * Gives the user confirmed MFA with the given secret.
 */
function withConfirmedMfa(User $user, string $secret): User
{
    $user->forceFill([
        'two_factor_secret' => Crypt::encrypt($secret),
        'two_factor_recovery_codes' => Crypt::encrypt(json_encode(['code-one-aaaa', 'code-two-bbbb'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user;
}

/**
 * @return array<string, mixed>
 */
function mfaColumns(User $user): array
{
    return (array) DB::table('users')->where('id', $user->getKey())
        ->first(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
}

/**
 * The session cookie that keeps one login session across test requests.
 *
 * @return array<string, string>
 */
function loginSession(?string $id = null): array
{
    return [(string) config('session.cookie') => $id ?? Str::random(40)];
}

describe('who may manage MFA', function () {
    it('refuses MFA to teachers and students with 403 and changes nothing', function (string $actor) {
        $user = $this->{$actor};
        $before = mfaColumns($user);

        // Even with a freshly confirmed password.
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        $this->get($this->tenantUrl($this->acme, 'settings/two-factor'))->assertForbidden();
        $this->post($this->tenantUrl($this->acme, 'user/two-factor-authentication'))->assertForbidden();
        $this->post($this->tenantUrl($this->acme, 'user/confirmed-two-factor-authentication'), ['code' => '123456'])->assertForbidden();
        $this->delete($this->tenantUrl($this->acme, 'user/two-factor-authentication'))->assertForbidden();
        $this->get($this->tenantUrl($this->acme, 'user/two-factor-qr-code'))->assertForbidden();
        $this->get($this->tenantUrl($this->acme, 'user/two-factor-secret-key'))->assertForbidden();
        $this->get($this->tenantUrl($this->acme, 'user/two-factor-recovery-codes'))->assertForbidden();
        $this->post($this->tenantUrl($this->acme, 'user/two-factor-recovery-codes'))->assertForbidden();

        expect(mfaColumns($user))->toBe($before)
            ->and($user->can('manageTwoFactor', User::class))->toBeFalse();
    })->with(['teacher', 'student']);

    it('offers MFA to org admins only', function () {
        expect($this->admin->can('manageTwoFactor', User::class))->toBeTrue();

        $this->admin->forceFill(['is_active' => false])->save();
        expect($this->admin->fresh()->can('manageTwoFactor', User::class))->toBeFalse();
    });

    it('sends guests to the login page', function () {
        $this->post($this->tenantUrl($this->acme, 'user/two-factor-authentication'))
            ->assertRedirect($this->tenantUrl($this->acme, 'login'));
        $this->get($this->tenantUrl($this->acme, 'settings/two-factor'))
            ->assertRedirect($this->tenantUrl($this->acme, 'login'));
    });
});

describe('enabling and disabling MFA', function () {
    it('asks an org admin for the password before the MFA settings', function () {
        $this->actingAs($this->admin)
            ->get($this->tenantUrl($this->acme, 'settings/two-factor'))
            ->assertRedirect($this->tenantUrl($this->acme, 'user/confirm-password'));

        $this->post($this->tenantUrl($this->acme, 'user/two-factor-authentication'))
            ->assertRedirect($this->tenantUrl($this->acme, 'user/confirm-password'));

        expect(mfaColumns($this->admin)['two_factor_secret'])->toBeNull();

        // A wrong password does not open them.
        $this->post($this->tenantUrl($this->acme, 'user/confirm-password'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');
        $this->get($this->tenantUrl($this->acme, 'settings/two-factor'))
            ->assertRedirect($this->tenantUrl($this->acme, 'user/confirm-password'));

        $this->post($this->tenantUrl($this->acme, 'user/confirm-password'), ['password' => UserFactory::PASSWORD])
            ->assertSessionHasNoErrors();
        $this->get($this->tenantUrl($this->acme, 'settings/two-factor'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Identity/Settings/TwoFactor')
                ->where('enabled', false)
                ->where('pending', false));
    });

    it('enables MFA only after the password and a valid first code', function () {
        $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);

        $this->post($this->tenantUrl($this->acme, 'user/two-factor-authentication'))->assertRedirect();

        $admin = $this->admin->fresh();
        $secret = Crypt::decrypt($admin->two_factor_secret);
        expect($secret)->toBeString()->not->toBeEmpty()
            ->and($admin->two_factor_confirmed_at)->toBeNull()
            ->and($admin->hasEnabledTwoFactorAuthentication())->toBeFalse();

        // Stored encrypted.
        $raw = mfaColumns($admin);
        expect($raw['two_factor_secret'])->not->toContain($secret)
            ->and($raw['two_factor_recovery_codes'])->not->toContain('-');

        $this->get($this->tenantUrl($this->acme, 'settings/two-factor'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pending', true)
                ->where('setupKey', $secret)
                ->where('qrCode', fn (string $qr) => str_starts_with($qr, 'data:image/svg+xml;base64,')));

        // A wrong first code does not enable MFA.
        $this->post($this->tenantUrl($this->acme, 'user/confirmed-two-factor-authentication'), ['code' => '000000'])
            ->assertSessionHasErrors(['code' => __('profile.two_factor.invalid_code')]);
        expect($this->admin->fresh()->two_factor_confirmed_at)->toBeNull();

        $this->post($this->tenantUrl($this->acme, 'user/confirmed-two-factor-authentication'), [
            'code' => $this->google2fa->getCurrentOtp($secret),
        ])->assertSessionHasNoErrors();

        expect($this->admin->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();

        // The recovery codes are sent only when the admin asks for them.
        $this->get($this->tenantUrl($this->acme, 'settings/two-factor'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('enabled', true)
                ->where('pending', false)
                ->where('setupKey', null)
                ->missing('recoveryCodes')
                ->reloadOnly('recoveryCodes', fn (Assert $reload) => $reload->has('recoveryCodes', 8)));
    });

    it('lets an org admin disable MFA', function () {
        withConfirmedMfa($this->admin, $this->google2fa->generateSecretKey());

        $this->actingAs($this->admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete($this->tenantUrl($this->acme, 'user/two-factor-authentication'))
            ->assertRedirect();

        expect(mfaColumns($this->admin))->toBe([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    });

    it('never exposes the MFA secret or recovery codes in the user\'s array form', function () {
        withConfirmedMfa($this->admin, $this->google2fa->generateSecretKey());

        expect($this->admin->fresh()->toArray())
            ->not->toHaveKey('two_factor_secret')
            ->not->toHaveKey('two_factor_recovery_codes')
            ->not->toHaveKey('password');
    });
});

describe('logging in with MFA', function () {
    it('asks an org admin with MFA for a code, then logs them in', function () {
        $secret = $this->google2fa->generateSecretKey();
        withConfirmedMfa($this->admin, $secret);
        $session = loginSession();

        $this->withCookies($session)
            ->post($this->tenantUrl($this->acme, 'login'), ['email' => 'alice@acme.test', 'password' => UserFactory::PASSWORD])
            ->assertRedirect($this->tenantUrl($this->acme, 'two-factor-challenge'));
        $this->assertGuest();

        $this->withCookies($session)
            ->get($this->tenantUrl($this->acme, 'two-factor-challenge'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/TwoFactorChallenge'));

        $this->withCookies($session)
            ->post($this->tenantUrl($this->acme, 'two-factor-challenge'), ['code' => $this->google2fa->getCurrentOtp($secret)])
            ->assertRedirect($this->tenantUrl($this->acme, 'dashboard'));
        $this->assertAuthenticatedAs($this->admin);
    });

    it('accepts a recovery code once', function () {
        withConfirmedMfa($this->admin, $this->google2fa->generateSecretKey());
        $session = loginSession();

        $this->withCookies($session)
            ->post($this->tenantUrl($this->acme, 'login'), ['email' => 'alice@acme.test', 'password' => UserFactory::PASSWORD]);
        $this->withCookies($session)
            ->post($this->tenantUrl($this->acme, 'two-factor-challenge'), ['recovery_code' => 'code-one-aaaa'])
            ->assertRedirect($this->tenantUrl($this->acme, 'dashboard'));

        $this->assertAuthenticatedAs($this->admin);
        expect($this->admin->fresh()->recoveryCodes())->not->toContain('code-one-aaaa');
    });

    it('rejects a wrong code with a translated message', function () {
        withConfirmedMfa($this->admin, $this->google2fa->generateSecretKey());
        $session = loginSession();

        $this->withCookies($session)
            ->post($this->tenantUrl($this->acme, 'login'), ['email' => 'alice@acme.test', 'password' => UserFactory::PASSWORD]);
        $this->withCookies($session)
            ->post($this->tenantUrl($this->acme, 'two-factor-challenge'), ['code' => '000000'])
            ->assertRedirect($this->tenantUrl($this->acme, 'two-factor-challenge'))
            ->assertSessionHasErrors(['code' => __('profile.two_factor.invalid_code')]);

        $this->assertGuest();
    });

    it('throttles the 6th wrong code within a minute in the same login session', function () {
        withConfirmedMfa($this->admin, $this->google2fa->generateSecretKey());
        $session = loginSession();

        $this->withCookies($session)
            ->post($this->tenantUrl($this->acme, 'login'), ['email' => 'alice@acme.test', 'password' => UserFactory::PASSWORD])
            ->assertRedirect($this->tenantUrl($this->acme, 'two-factor-challenge'));

        foreach (range(1, 5) as $attempt) {
            $this->withCookies($session)
                ->post($this->tenantUrl($this->acme, 'two-factor-challenge'), ['code' => '000000'])
                ->assertRedirect($this->tenantUrl($this->acme, 'two-factor-challenge'));
        }

        $this->withCookies($session)
            ->post($this->tenantUrl($this->acme, 'two-factor-challenge'), ['code' => '000000'])
            ->assertTooManyRequests();
        $this->assertGuest();

        // The limit belongs to that login session.
        $other = loginSession();
        $this->withCookies($other)
            ->post($this->tenantUrl($this->acme, 'login'), ['email' => 'alice@acme.test', 'password' => UserFactory::PASSWORD]);
        $this->withCookies($other)
            ->post($this->tenantUrl($this->acme, 'two-factor-challenge'), ['code' => '000000'])
            ->assertRedirect($this->tenantUrl($this->acme, 'two-factor-challenge'));

        // A minute later the session may try again.
        $this->travel(61)->seconds();
        $this->withCookies($session)
            ->post($this->tenantUrl($this->acme, 'two-factor-challenge'), ['code' => '000000'])
            ->assertRedirect($this->tenantUrl($this->acme, 'two-factor-challenge'));
    });

    it('does not ask users without MFA for a code', function () {
        $this->post($this->tenantUrl($this->acme, 'login'), ['email' => 'tom@acme.test', 'password' => UserFactory::PASSWORD])
            ->assertRedirect($this->tenantUrl($this->acme, 'dashboard'));

        $this->assertAuthenticatedAs($this->teacher);
    });

    it('does not ask for a code while MFA is not yet confirmed', function () {
        $this->admin->forceFill(['two_factor_secret' => Crypt::encrypt($this->google2fa->generateSecretKey())])->save();

        $this->post($this->tenantUrl($this->acme, 'login'), ['email' => 'alice@acme.test', 'password' => UserFactory::PASSWORD])
            ->assertRedirect($this->tenantUrl($this->acme, 'dashboard'));

        $this->assertAuthenticatedAs($this->admin);
    });
});

describe('demotion', function () {
    it('clears the MFA of an org admin demoted to teacher, who then logs in without a code', function () {
        withConfirmedMfa($this->admin, $this->google2fa->generateSecretKey());

        $this->actingAs($this->otherAdmin)
            ->patch($this->tenantUrl($this->acme, "users/{$this->admin->getKey()}/role"), ['role' => 'teacher'])
            ->assertSessionHasNoErrors();

        expect($this->admin->fresh()->role)->toBe(Role::Teacher)
            ->and(mfaColumns($this->admin))->toBe([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ]);

        auth()->guard('web')->logout();
        $this->flushSession();

        $this->post($this->tenantUrl($this->acme, 'login'), ['email' => 'alice@acme.test', 'password' => UserFactory::PASSWORD])
            ->assertRedirect($this->tenantUrl($this->acme, 'dashboard'));
        $this->assertAuthenticatedAs($this->admin->fresh());
    });

    it('keeps role and MFA together when the demotion is refused', function () {
        // The last active org admin cannot be demoted; their MFA stays.
        $this->otherAdmin->forceFill(['is_active' => false])->save();
        withConfirmedMfa($this->admin, $this->google2fa->generateSecretKey());
        $before = mfaColumns($this->admin);

        $this->actingAs($this->admin)
            ->patch($this->tenantUrl($this->acme, "users/{$this->admin->getKey()}/role"), ['role' => 'teacher'])
            ->assertSessionHasErrors('role');

        expect($this->admin->fresh()->role)->toBe(Role::OrgAdmin)
            ->and(mfaColumns($this->admin))->toBe($before);
    });

    it('leaves the MFA columns of users who do not stop being org admins alone', function () {
        $this->actingAs($this->admin)
            ->patch($this->tenantUrl($this->acme, "users/{$this->teacher->getKey()}/role"), ['role' => 'org_admin'])
            ->assertSessionHasNoErrors();

        expect($this->teacher->fresh()->role)->toBe(Role::OrgAdmin);
    });
});

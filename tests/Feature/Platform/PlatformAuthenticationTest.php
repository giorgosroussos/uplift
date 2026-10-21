<?php

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Actions\AttemptPlatformLogin;
use App\Modules\Platform\Database\Factories\PlatformAdminFactory;
use App\Modules\Platform\Models\PlatformAdmin;
use App\Modules\Platform\Support\PlatformSession;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\CountingHasher;

/*
| P8-T1 — Platform admins (ENT-PlatformAdmin) live in their own table and log
| in with a separate guard on the apex domain only (SEC-006); TOTP MFA is
| required for them (SEC-011); login is limited to 5 failed attempts per
| minute per email and the TOTP step to 5 attempts per minute per login
| session (SEC-010, SEC-012); passwords are argon2id hashes (SEC-010).
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->google2fa = new Google2FA;
    $this->secret = $this->google2fa->generateSecretKey();

    $this->admin = PlatformAdmin::factory()->withTwoFactor($this->secret)->create(['email' => 'root@uplift.test']);
    $this->newAdmin = PlatformAdmin::factory()->create(['email' => 'new@uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->orgAdmin = User::factory()->for($this->acme, 'organization')->orgAdmin()->create(['email' => 'alice@acme.test']);

    $this->platformSession = Str::random(40);
});

/**
 * Sends a request in the test's one browser session, following the session
 * id when the application regenerates it.
 *
 * @param  array<string, mixed>  $data
 */
function platformSend(string $method, string $url, array $data = []): TestResponse
{
    $test = test();
    $test->withCookies([(string) config('session.cookie') => $test->platformSession]);
    $response = $method === 'GET' ? $test->get($url) : $test->post($url, $data);

    $test->platformSession = app('session')->driver()->getId();

    return $response;
}

function platformPasswordStep(string $email = 'root@uplift.test', string $password = PlatformAdminFactory::PASSWORD): TestResponse
{
    return platformSend('POST', test()->apexUrl('admin/login'), ['email' => $email, 'password' => $password]);
}

function platformOtp(string $secret): string
{
    return (new Google2FA)->getCurrentOtp($secret);
}

describe('the platform admin table and guard', function () {
    it('keeps platform admins in their own table, never in users', function () {
        expect(Schema::hasTable('platform_admins'))->toBeTrue()
            ->and(DB::table('users')->where('email', 'root@uplift.test')->exists())->toBeFalse()
            ->and(DB::table('platform_admins')->where('email', 'root@uplift.test')->exists())->toBeTrue()
            ->and(Schema::hasColumn('platform_admins', 'organization_id'))->toBeFalse()
            ->and(Str::isUuid($this->admin->getKey()))->toBeTrue();
    });

    it('uses a separate session guard with its own provider', function () {
        expect(config('auth.guards.platform'))->toBe(['driver' => 'session', 'provider' => 'platform_admins'])
            ->and(config('auth.providers.platform_admins.model'))->toBe(PlatformAdmin::class)
            ->and(config('auth.defaults.guard'))->toBe('web');
    });

    it('stores the password as an argon2id hash and the MFA secret encrypted', function () {
        $row = DB::table('platform_admins')->where('id', $this->admin->getKey())->first();

        expect($row->password)->toStartWith('$argon2id$')
            ->and(Hash::check(PlatformAdminFactory::PASSWORD, $row->password))->toBeTrue()
            ->and($row->two_factor_secret)->not->toContain($this->secret)
            ->and(Fortify::currentEncrypter()->decrypt($row->two_factor_secret))->toBe($this->secret)
            ->and($row->two_factor_recovery_codes)->not->toContain(PlatformAdminFactory::RECOVERY_CODES[0]);
    });

    it('stores emails lower-cased and keeps them unique', function () {
        $admin = PlatformAdmin::factory()->create(['email' => '  Mixed@Uplift.TEST ']);

        expect($admin->email)->toBe('mixed@uplift.test');
        // Each attempt in its own savepoint, so the failure leaves the test's
        // transaction usable.
        expect(fn () => DB::transaction(fn () => DB::table('platform_admins')->insert([
            'id' => (string) Str::uuid(), 'name' => 'X', 'email' => 'Upper@uplift.test', 'password' => 'x',
        ])))->toThrow(QueryException::class, 'platform_admins_email_lowercase_check');
        expect(fn () => DB::transaction(fn () => PlatformAdmin::factory()->create(['email' => 'MIXED@uplift.test'])))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('never serialises personal data or secrets', function () {
        $json = json_encode($this->admin->fresh());

        expect($json)->not->toContain('root@uplift.test')
            ->not->toContain($this->admin->name)
            ->not->toContain('password')
            ->not->toContain('two_factor_secret')
            ->not->toContain('two_factor_recovery_codes');
    });
});

describe('the apex domain only', function () {
    it('shows the platform login on the apex domain', function () {
        $this->get($this->apexUrl('admin/login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Platform/Login'));
    });

    it('does not exist on an organisation subdomain, a reserved subdomain or another host', function (string $host) {
        $paths = ['admin/login', 'admin', 'admin/two-factor-challenge', 'admin/two-factor-setup'];

        foreach ($paths as $path) {
            $this->get('http://'.$host.'/'.$path)->assertNotFound();
        }

        $this->post('http://'.$host.'/admin/login', ['email' => 'root@uplift.test', 'password' => PlatformAdminFactory::PASSWORD])
            ->assertNotFound();
        $this->assertGuest('platform');
    })->with(['organisation subdomain' => 'acme.uplift.test', 'reserved subdomain' => 'admin.uplift.test', 'other host' => 'example.com']);
});

describe('logging in with required MFA', function () {
    it('asks an admin with MFA for a TOTP code before logging them in', function () {
        platformPasswordStep()->assertRedirect($this->apexUrl('admin/two-factor-challenge'));
        $this->assertGuest('platform');

        // The password alone does not open the platform.
        platformSend('GET', $this->apexUrl('admin'))->assertRedirect($this->apexUrl('admin/login'));

        platformSend('GET', $this->apexUrl('admin/two-factor-challenge'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Platform/TwoFactorChallenge'));

        platformSend('POST', $this->apexUrl('admin/two-factor-challenge'), ['code' => platformOtp($this->secret)])
            ->assertRedirect($this->apexUrl('admin'));
        $this->assertAuthenticatedAs($this->admin, 'platform');

        platformSend('GET', $this->apexUrl('admin'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Platform/Dashboard')
                ->where('admin.name', $this->admin->name)
                ->where('auth.user', null));
    });

    it('compares the email case-insensitively', function () {
        platformPasswordStep('  ROOT@Uplift.Test ')->assertRedirect($this->apexUrl('admin/two-factor-challenge'));
    });

    it('rejects a wrong TOTP code and does not log in', function () {
        platformPasswordStep();

        platformSend('POST', $this->apexUrl('admin/two-factor-challenge'), ['code' => '000000'])
            ->assertSessionHasErrors(['code' => __('platform.two_factor.invalid_code')]);

        $this->assertGuest('platform');
        platformSend('GET', $this->apexUrl('admin'))->assertRedirect($this->apexUrl('admin/login'));
    });

    it('accepts a TOTP code only once', function () {
        $code = platformOtp($this->secret);
        platformPasswordStep();
        platformSend('POST', $this->apexUrl('admin/two-factor-challenge'), ['code' => $code])->assertRedirect($this->apexUrl('admin'));
        platformSend('POST', $this->apexUrl('admin/logout'))->assertRedirect($this->apexUrl('admin/login'));

        platformPasswordStep();
        platformSend('POST', $this->apexUrl('admin/two-factor-challenge'), ['code' => $code])
            ->assertSessionHasErrors('code');
        expect(PlatformSession::admin())->toBeNull();
    });

    it('accepts a recovery code once', function () {
        $code = PlatformAdminFactory::RECOVERY_CODES[0];

        platformPasswordStep();
        platformSend('POST', $this->apexUrl('admin/two-factor-challenge'), ['recovery_code' => $code])
            ->assertRedirect($this->apexUrl('admin'));
        $this->assertAuthenticatedAs($this->admin, 'platform');
        expect($this->admin->fresh()->recoveryCodes())->not->toContain($code)->toHaveCount(2);

        platformSend('POST', $this->apexUrl('admin/logout'));
        platformPasswordStep();
        platformSend('POST', $this->apexUrl('admin/two-factor-challenge'), ['recovery_code' => $code])
            ->assertSessionHasErrors(['recovery_code' => __('platform.two_factor.invalid_recovery_code')]);
        expect(PlatformSession::admin())->toBeNull();
    });

    it('throttles the 6th TOTP attempt within a minute in the same login session', function () {
        platformPasswordStep();

        foreach (range(1, 5) as $attempt) {
            platformSend('POST', $this->apexUrl('admin/two-factor-challenge'), ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        platformSend('POST', $this->apexUrl('admin/two-factor-challenge'), ['code' => platformOtp($this->secret)])
            ->assertTooManyRequests();
        $this->assertGuest('platform');

        $this->travel(61)->seconds();
        platformSend('POST', $this->apexUrl('admin/two-factor-challenge'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
    });

    it('refuses the TOTP step without a passed password step', function () {
        platformSend('GET', $this->apexUrl('admin/two-factor-challenge'))->assertRedirect($this->apexUrl('admin/login'));
        platformSend('POST', $this->apexUrl('admin/two-factor-challenge'), ['code' => platformOtp($this->secret)])
            ->assertRedirect($this->apexUrl('admin/login'));
        platformSend('GET', $this->apexUrl('admin/two-factor-setup'))->assertRedirect($this->apexUrl('admin/login'));

        $this->assertGuest('platform');
    });

    it('logs out', function () {
        platformPasswordStep();
        platformSend('POST', $this->apexUrl('admin/two-factor-challenge'), ['code' => platformOtp($this->secret)]);

        platformSend('POST', $this->apexUrl('admin/logout'))->assertRedirect($this->apexUrl('admin/login'));

        $this->assertGuest('platform');
        platformSend('GET', $this->apexUrl('admin'))->assertRedirect($this->apexUrl('admin/login'));
    });

    it('sends a logged-in admin from the login page to the platform', function () {
        $this->actingAs($this->admin, 'platform')
            ->get($this->apexUrl('admin/login'))
            ->assertRedirect($this->apexUrl('admin'));
    });
});

describe('first login without MFA', function () {
    it('makes the admin set up MFA before entering the platform', function () {
        platformPasswordStep('new@uplift.test')->assertRedirect($this->apexUrl('admin/two-factor-setup'));
        $this->assertGuest('platform');

        // Not past the setup: the challenge sends back to the setup.
        platformSend('GET', $this->apexUrl('admin/two-factor-challenge'))->assertRedirect($this->apexUrl('admin/two-factor-setup'));

        platformSend('GET', $this->apexUrl('admin/two-factor-setup'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Platform/TwoFactorSetup')
                ->where('qrCode', fn (string $qr) => str_starts_with($qr, 'data:image/svg+xml;base64,'))
                ->has('setupKey'));

        $admin = $this->newAdmin->fresh();
        expect($admin->two_factor_secret)->not->toBeNull()
            ->and($admin->two_factor_confirmed_at)->toBeNull();
        $secret = Fortify::currentEncrypter()->decrypt($admin->two_factor_secret);

        // Reloading the page keeps the same secret.
        platformSend('GET', $this->apexUrl('admin/two-factor-setup'))
            ->assertInertia(fn (Assert $page) => $page->where('setupKey', $secret));

        platformSend('POST', $this->apexUrl('admin/two-factor-setup'), ['code' => platformOtp($secret)])
            ->assertRedirect($this->apexUrl('admin/recovery-codes'));
        $this->assertAuthenticatedAs($this->newAdmin, 'platform');
        expect($this->newAdmin->fresh()->hasConfirmedTwoFactor())->toBeTrue();

        // The recovery codes are shown once.
        platformSend('GET', $this->apexUrl('admin/recovery-codes'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Platform/RecoveryCodes')
                ->where('recoveryCodes', $this->newAdmin->fresh()->recoveryCodes())
                ->has('recoveryCodes', 8));
        platformSend('GET', $this->apexUrl('admin/recovery-codes'))->assertRedirect($this->apexUrl('admin'));
    });

    it('does not confirm MFA or log in with a wrong first code', function () {
        platformPasswordStep('new@uplift.test');
        platformSend('GET', $this->apexUrl('admin/two-factor-setup'));

        platformSend('POST', $this->apexUrl('admin/two-factor-setup'), ['code' => '000000'])
            ->assertSessionHasErrors(['code' => __('platform.two_factor.invalid_code')]);

        $this->assertGuest('platform');
        expect($this->newAdmin->fresh()->two_factor_confirmed_at)->toBeNull();
        platformSend('GET', $this->apexUrl('admin'))->assertRedirect($this->apexUrl('admin/login'));
    });

    it('does not accept recovery codes before MFA is confirmed', function () {
        platformPasswordStep('new@uplift.test');
        platformSend('GET', $this->apexUrl('admin/two-factor-setup'));
        $codes = $this->newAdmin->fresh()->recoveryCodes();

        platformSend('POST', $this->apexUrl('admin/two-factor-setup'), ['recovery_code' => $codes[0]])
            ->assertSessionHasErrors(['code', 'recovery_code']);
        platformSend('POST', $this->apexUrl('admin/two-factor-challenge'), ['recovery_code' => $codes[0]])
            ->assertSessionHasErrors('code');

        $this->assertGuest('platform');
    });

    it('throttles the 6th setup attempt within a minute in the same login session', function () {
        platformPasswordStep('new@uplift.test');
        platformSend('GET', $this->apexUrl('admin/two-factor-setup'));

        foreach (range(1, 5) as $attempt) {
            platformSend('POST', $this->apexUrl('admin/two-factor-setup'), ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        platformSend('POST', $this->apexUrl('admin/two-factor-setup'), ['code' => '000000'])->assertTooManyRequests();
    });

    it('never replaces the confirmed MFA of an admin through the setup', function () {
        $before = DB::table('platform_admins')->where('id', $this->admin->getKey())->value('two_factor_secret');

        platformPasswordStep();
        platformSend('GET', $this->apexUrl('admin/two-factor-setup'))->assertRedirect($this->apexUrl('admin/two-factor-challenge'));
        platformSend('POST', $this->apexUrl('admin/two-factor-setup'), ['code' => platformOtp($this->secret)])
            ->assertRedirect($this->apexUrl('admin/two-factor-challenge'));

        $this->assertGuest('platform');
        expect(DB::table('platform_admins')->where('id', $this->admin->getKey())->value('two_factor_secret'))->toBe($before);
    });

    it('logs out an admin whose MFA is no longer confirmed', function () {
        $this->actingAs($this->newAdmin, 'platform')
            ->get($this->apexUrl('admin'))
            ->assertRedirect($this->apexUrl('admin/login'));

        $this->assertGuest('platform');
    });
});

describe('the password step', function () {
    it('rejects a wrong password and an unknown email with the same message', function (string $email, string $password) {
        platformPasswordStep($email, $password)
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest('platform');
        platformSend('GET', $this->apexUrl('admin/two-factor-challenge'))->assertRedirect($this->apexUrl('admin/login'));
    })->with([
        'wrong password' => ['root@uplift.test', 'not-the-password'],
        'unknown email' => ['nobody@uplift.test', PlatformAdminFactory::PASSWORD],
    ]);

    it('does not let an organisation user log in to the platform', function () {
        platformPasswordStep('alice@acme.test', 'correct-horse-battery')
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest('platform');
    });

    it('does the same hashing work for an unknown email as for a known one', function () {
        $hasher = new CountingHasher(app('hash'));
        app()->instance('hash.driver', $hasher);
        app()->forgetInstance(AttemptPlatformLogin::class);

        platformPasswordStep('nobody@uplift.test', 'whatever-password');
        $unknown = $hasher->checks;

        platformPasswordStep('root@uplift.test', 'whatever-password');

        expect($unknown)->toBe(1)->and($hasher->checks - $unknown)->toBe(1);
    });

    it('throttles the 6th login attempt within a minute per email, even with the right password', function () {
        foreach (range(1, 5) as $attempt) {
            platformPasswordStep('root@uplift.test', 'wrong-password')
                ->assertSessionHasErrors(['email' => __('auth.failed')]);
        }

        $response = platformPasswordStep();
        $response->assertSessionHasErrors('email');
        expect(session('errors')->first('email'))->toStartWith(Str::before(__('auth.throttle'), ':seconds'));
        platformSend('GET', $this->apexUrl('admin/two-factor-challenge'))->assertRedirect($this->apexUrl('admin/login'));

        // The limit is per email, not per client.
        platformPasswordStep('new@uplift.test')->assertRedirect($this->apexUrl('admin/two-factor-setup'));

        // ... and lasts a minute.
        $this->travel(61)->seconds();
        $this->platformSession = Str::random(40);
        platformPasswordStep()->assertRedirect($this->apexUrl('admin/two-factor-challenge'));
    });

    it('does not put the email or the password in error messages', function () {
        platformPasswordStep('root@uplift.test', 'wrong-password')->assertSessionHasErrors('email');

        $messages = implode(' ', session('errors')->getBag('default')->all());

        expect($messages)->not->toContain('root@uplift.test')->not->toContain('wrong-password');
    });
});

describe('guards do not cross', function () {
    it('does not let an organisation user into the platform', function () {
        $this->actingAs($this->orgAdmin)
            ->get($this->apexUrl('admin'))
            ->assertRedirect($this->apexUrl('admin/login'));
    });

    it('does not let a platform admin act as an organisation user', function () {
        platformPasswordStep();
        platformSend('POST', $this->apexUrl('admin/two-factor-challenge'), ['code' => platformOtp($this->secret)]);
        $this->assertAuthenticatedAs($this->admin, 'platform');

        platformSend('GET', $this->tenantUrl($this->acme, 'dashboard'))
            ->assertRedirect($this->tenantUrl($this->acme, 'login'));

        $this->assertGuest('web');
    });
});

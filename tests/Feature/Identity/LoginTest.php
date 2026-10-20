<?php

use App\Modules\Identity\Actions\AuthenticateUser;
use App\Modules\Identity\Contracts\IdentityProviderResolver;
use App\Modules\Identity\Database\Factories\UserFactory;
use App\Modules\Identity\Exceptions\IdentityProviderUnavailable;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\NoIdentityProviders;
use App\Modules\Tenancy\Enums\OrganizationStatus;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ViewErrorBag;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-003 — Log in with email and password on the organisation subdomain.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme', 'name' => 'Acme School']);
    $this->beta = $this->createOrganization(['subdomain' => 'beta']);

    $this->teacher = User::factory()->for($this->acme, 'organization')->teacher()->create([
        'email' => 'teacher@acme.test',
    ]);
});

/**
 * @return array<string, string>
 */
function credentials(string $email = 'teacher@acme.test', string $password = UserFactory::PASSWORD): array
{
    return ['email' => $email, 'password' => $password];
}

/**
 * Validation errors of the last request, as field => messages.
 *
 * @return array<string, list<string>>
 */
function loginErrors(): array
{
    $errors = session('errors');

    // With JSON session serialisation the bag comes back as an array.
    return $errors instanceof ViewErrorBag
        ? $errors->getBag('default')->toArray()
        : (array) ($errors['default']['messages'] ?? []);
}

it('shows the login page with the organisation on its subdomain', function () {
    $this->get($this->tenantUrl($this->acme, 'login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('organization.name', 'Acme School')
            ->where('identityProvidersUnavailable', false));
});

it('has no login on the apex domain', function () {
    $this->get($this->apexUrl('login'))->assertNotFound();
    $this->post($this->apexUrl('login'), credentials())->assertNotFound();
    $this->assertGuest();
});

it('logs a teacher of acme in on acme\'s subdomain and leads them to the teacher dashboard', function () {
    $this->post($this->tenantUrl($this->acme, 'login'), credentials())
        ->assertSessionHasNoErrors()
        ->assertRedirect($this->tenantUrl($this->acme, 'dashboard'));

    $this->assertAuthenticatedAs($this->teacher);

    $this->get($this->tenantUrl($this->acme, 'dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Identity/TeacherDashboard'));
});

it('leads every role to its own dashboard', function (string $state, string $component) {
    $user = User::factory()->for($this->acme, 'organization')->{$state}()->create();

    $this->actingAs($user)
        ->get($this->tenantUrl($this->acme, 'dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'student' => ['student', 'Identity/StudentDashboard'],
    'teacher' => ['teacher', 'Identity/TeacherDashboard'],
    'org admin' => ['orgAdmin', 'Identity/OrgAdminDashboard'],
]);

it('compares emails case-insensitively', function () {
    $this->post($this->tenantUrl($this->acme, 'login'), credentials('  Teacher@ACME.test'))
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($this->teacher);
});

it('fails with a generic error on a wrong password', function () {
    $this->post($this->tenantUrl($this->acme, 'login'), credentials(password: 'wrong-password'))
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

it('fails for a teacher of acme on beta\'s subdomain exactly like a wrong password', function () {
    User::factory()->for($this->beta, 'organization')->teacher()->create(['email' => 'teacher@beta.test']);

    $wrongPassword = $this->from($this->tenantUrl($this->beta, 'login'))
        ->post($this->tenantUrl($this->beta, 'login'), credentials('teacher@beta.test', 'wrong-password'));
    $wrongPasswordErrors = loginErrors();

    $otherOrganisation = $this->from($this->tenantUrl($this->beta, 'login'))
        ->post($this->tenantUrl($this->beta, 'login'), credentials());
    $otherOrganisationErrors = loginErrors();

    expect($otherOrganisation->getStatusCode())->toBe($wrongPassword->getStatusCode())
        ->and($otherOrganisation->headers->get('Location'))->toBe($wrongPassword->headers->get('Location'))
        ->and($otherOrganisationErrors)->toBe($wrongPasswordErrors)
        ->and($otherOrganisationErrors)->toBe(['email' => [__('auth.failed')]]);

    $this->assertGuest();
});

it('refuses a deactivated user with correct credentials', function () {
    $this->teacher->forceFill(['is_active' => false])->save();

    $this->post($this->tenantUrl($this->acme, 'login'), credentials())
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('refuses a user of a suspended organisation with correct credentials', function () {
    $this->acme->transitionTo(OrganizationStatus::Suspended);

    $this->post($this->tenantUrl($this->acme, 'login'), credentials())
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('ends the session of a user deactivated after logging in', function () {
    $this->actingAs($this->teacher)->get($this->tenantUrl($this->acme, 'dashboard'))->assertOk();

    $this->teacher->forceFill(['is_active' => false])->save();

    $this->get($this->tenantUrl($this->acme, 'dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('refuses a user without a password (SSO-only account)', function () {
    $user = User::factory()->for($this->acme, 'organization')->withoutPassword()->create(['email' => 'sso@acme.test']);

    expect($user->fresh()->password)->toBeNull();

    $this->post($this->tenantUrl($this->acme, 'login'), credentials('sso@acme.test', ''))
        ->assertSessionHasErrors();
    $this->assertGuest();
});

it('rejects a 6th attempt within the minute after 5 failed ones without checking the password', function () {
    $this->freezeTime();

    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])
            ->post($this->tenantUrl($this->acme, 'login'), credentials(password: 'wrong-password'))
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    // The password is not checked any more: the authentication action is not run.
    $this->mock(AuthenticateUser::class)->shouldNotReceive('handle');

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
        ->post($this->tenantUrl($this->acme, 'login'), credentials())
        ->assertSessionHasErrors(['email' => __('auth.throttle', ['seconds' => 60])]);

    $this->assertGuest();
});

it('lets the email log in again after the minute', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post($this->tenantUrl($this->acme, 'login'), credentials(password: 'wrong-password'));
    }

    $this->travel(61)->seconds();

    $this->post($this->tenantUrl($this->acme, 'login'), credentials())->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($this->teacher);
});

it('hashes passwords with argon2id', function () {
    $user = User::factory()->for($this->acme, 'organization')->create(['password' => 'another-secret']);

    expect(Hash::info($user->password)['algoName'])->toBe('argon2id')
        ->and(Hash::check('another-secret', $user->password))->toBeTrue();
});

it('scopes the session cookie to the organisation host', function () {
    $response = $this->get($this->tenantUrl($this->acme, 'login'));

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->getDomain())->toBeNull()
        ->and(config('session.domain'))->toBeNull();
});

it('does not know the session of acme on beta', function () {
    $this->post($this->tenantUrl($this->acme, 'login'), credentials())->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($this->teacher);

    // Same session, other organisation: acme's user is not visible there.
    app('auth')->forgetGuards();
    $this->get($this->tenantUrl($this->beta, 'dashboard'))->assertRedirect(route('login'));
});

describe('identity providers (INT-002)', function () {
    beforeEach(function () {
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
    });

    it('shows an error on the login page when the providers are unavailable or time out', function () {
        $this->get($this->tenantUrl($this->acme, 'login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->where('identityProvidersUnavailable', true)
                ->where('identityProviders', []));
    });

    it('still lets users with a password log in with email and password', function () {
        $this->post($this->tenantUrl($this->acme, 'login'), credentials())
            ->assertSessionHasNoErrors()
            ->assertRedirect($this->tenantUrl($this->acme, 'dashboard'));

        $this->assertAuthenticatedAs($this->teacher);
    });
});

it('has no identity providers and calls no external system while SSO is deferred', function () {
    $resolver = app(IdentityProviderResolver::class);

    expect($resolver)->toBeInstanceOf(NoIdentityProviders::class)
        ->and($resolver->providersFor((string) $this->acme->getKey()))->toBe([])
        ->and($resolver->resolveUser((string) $this->acme->getKey(), 'any', 'subject'))->toBeNull()
        ->and(config('identity.providers.timeout'))->toBe(10);
});

afterEach(function () {
    app(TenantContext::class)->clear();
});

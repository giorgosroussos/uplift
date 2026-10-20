<?php

use App\Modules\Identity\Actions\RegisterOrganization;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Events\OrganizationRegistered;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\VerifyEmailNotification;
use App\Modules\Tenancy\Enums\GradingScale;
use App\Modules\Tenancy\Enums\Locale;
use App\Modules\Tenancy\Enums\OrganizationStatus;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-001 — Register an organisation on the apex domain.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test', 'app.url' => 'http://uplift.test']);
    App::setLocale('en');
    Notification::fake();
});

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function signUp(array $overrides = []): array
{
    return [
        'organization_name' => 'Acme',
        'subdomain' => 'acme',
        'name' => 'Ada Admin',
        'email' => 'Ada@Acme.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        ...$overrides,
    ];
}

/**
 * Users of every organisation (the tenant scope is bypassed on purpose).
 */
function allUsers(): int
{
    return DB::table('users')->count();
}

it('shows the sign-up page on the apex domain', function () {
    $this->get($this->apexUrl('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Register')
            ->where('domain', 'uplift.test'));
});

it('has no sign-up on an organisation subdomain', function () {
    $beta = $this->createOrganization(['subdomain' => 'beta']);

    $this->get($this->tenantUrl($beta, 'register'))->assertNotFound();
    $this->post($this->tenantUrl($beta, 'register'), signUp())->assertNotFound();

    expect(Organization::query()->count())->toBe(1)->and(allUsers())->toBe(0);
});

it('creates an active organisation whose only user is its org admin', function () {
    $this->post($this->apexUrl('register'), signUp())->assertSessionHasNoErrors();

    $organization = Organization::query()->sole();

    expect($organization->subdomain)->toBe('acme')
        ->and($organization->name)->toBe('Acme')
        ->and($organization->status)->toBe(OrganizationStatus::Active)
        ->and(allUsers())->toBe(1);

    $admin = $this->asTenant($organization, fn () => User::query()->sole());

    expect($admin->role)->toBe(Role::OrgAdmin)
        ->and($admin->organization_id)->toBe($organization->id)
        ->and($admin->name)->toBe('Ada Admin')
        ->and($admin->email)->toBe('ada@acme.test')
        ->and($admin->email_verified_at)->toBeNull()
        ->and(Hash::check('correct-horse-battery', (string) $admin->password))->toBeTrue();
});

it('rejects a subdomain that is already taken, in any case, and creates nothing', function (string $subdomain) {
    $this->createOrganization(['subdomain' => 'acme']);

    $this->post($this->apexUrl('register'), signUp(['subdomain' => $subdomain]))
        ->assertSessionHasErrors(['subdomain' => 'This subdomain is already taken.']);

    expect(Organization::query()->count())->toBe(1)->and(allUsers())->toBe(0);
    Notification::assertNothingSent();
})->with(['acme', 'ACME']);

it('rejects the reserved subdomains', function (string $subdomain) {
    $this->post($this->apexUrl('register'), signUp(['subdomain' => $subdomain]))
        ->assertSessionHasErrors(['subdomain' => 'This subdomain is reserved by the platform.']);

    expect(Organization::query()->count())->toBe(0)->and(allUsers())->toBe(0);
})->with(['www', 'app', 'api', 'admin', 'mail']);

it('rejects malformed subdomains', function (string $subdomain) {
    $this->post($this->apexUrl('register'), signUp(['subdomain' => $subdomain]))
        ->assertSessionHasErrors(['subdomain' => 'Use 3 to 30 characters: lowercase letters, digits and hyphens, not at the start or end.']);

    expect(Organization::query()->count())->toBe(0)->and(allUsers())->toBe(0);
})->with([
    '2 characters' => ['ab'],
    'leading hyphen' => ['-acme'],
    'trailing hyphen' => ['acme-'],
    'underscore' => ['acme_1'],
    '31 characters' => [str_repeat('a', 31)],
]);

it('accepts a subdomain of 3-30 characters a-z, 0-9 and inner hyphens', function (string $subdomain) {
    $this->post($this->apexUrl('register'), signUp(['subdomain' => $subdomain]))->assertSessionHasNoErrors();

    expect(Organization::query()->sole()->subdomain)->toBe($subdomain);
})->with([
    'inner hyphen' => ['acme-1'],
    '3 characters' => ['abc'],
    '30 characters' => [str_repeat('a', 30)],
]);

it('requires every field and a strong, confirmed password', function (array $overrides, string $field) {
    $this->post($this->apexUrl('register'), signUp($overrides))->assertSessionHasErrors($field);

    expect(Organization::query()->count())->toBe(0)->and(allUsers())->toBe(0);
})->with([
    'no organisation name' => [['organization_name' => ''], 'organization_name'],
    'no subdomain' => [['subdomain' => ''], 'subdomain'],
    'no admin name' => [['name' => ''], 'name'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'compromised password' => [['password' => 'password123', 'password_confirmation' => 'password123'], 'password'],
    'unconfirmed password' => [['password_confirmation' => 'something-else'], 'password'],
]);

it('gives a registration from the Greek UI the initial settings and Greek as default locale', function () {
    App::setLocale('el');

    $this->post($this->apexUrl('register'), signUp())->assertSessionHasNoErrors();

    $organization = Organization::query()->sole();

    expect($organization->student_term_el)->toBe('Μαθητής')
        ->and($organization->student_term_en)->toBe('Student')
        ->and($organization->teacher_term_el)->toBe('Καθηγητής')
        ->and($organization->teacher_term_en)->toBe('Teacher')
        ->and($organization->grading_scale)->toBe(GradingScale::Scale0To10)
        ->and($organization->leaderboard_enabled)->toBeTrue()
        ->and($organization->default_locale)->toBe(Locale::Greek);
});

it('gives a registration from the English UI English as default locale', function () {
    $this->post($this->apexUrl('register'), signUp())->assertSessionHasNoErrors();

    $organization = Organization::query()->sole();

    expect($organization->default_locale)->toBe(Locale::English)
        ->and($organization->grading_scale)->toBe(GradingScale::Scale0To10)
        ->and($organization->leaderboard_enabled)->toBeTrue();
});

it('queues one verification mail to the admin and lands on the organisation login page with the notice', function () {
    $response = $this->post($this->apexUrl('register'), signUp());

    $organization = Organization::query()->sole();
    $admin = $this->asTenant($organization, fn () => User::query()->sole());

    $response->assertRedirect($this->tenantUrl($organization, 'login?verification=sent'));

    Notification::assertSentTimes(VerifyEmailNotification::class, 1);
    Notification::assertSentTo($admin, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($organization, $admin): bool {
        expect($notification)->toBeInstanceOf(ShouldQueue::class)
            ->and($notification->url)->toStartWith($this->tenantUrl($organization, "email/verify/{$admin->id}/"));

        return true;
    });

    $this->get($this->tenantUrl($organization, 'login?verification=sent'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('organization.name', 'Acme')
            ->where('verificationSent', true));
});

it('sends an Inertia visit to the organisation subdomain as an external location', function () {
    $this->post($this->apexUrl('register'), signUp(), ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', $this->tenantUrl(Organization::query()->sole(), 'login?verification=sent'));
});

it('does not show the verification notice on a plain login page', function () {
    $acme = $this->createOrganization(['subdomain' => 'acme']);

    $this->get($this->tenantUrl($acme, 'login'))
        ->assertInertia(fn (Assert $page) => $page->where('verificationSent', false));
});

it('lets the new admin log in on the subdomain and keeps them on the verify-email page until verified', function () {
    $this->post($this->apexUrl('register'), signUp());
    $organization = Organization::query()->sole();

    $this->post($this->tenantUrl($organization, 'login'), ['email' => 'ada@acme.test', 'password' => 'correct-horse-battery'])
        ->assertSessionHasNoErrors();

    $this->get($this->tenantUrl($organization, 'dashboard'))
        ->assertRedirect($this->tenantUrl($organization, 'email/verify'));

    $this->get($this->tenantUrl($organization, 'email/verify'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/VerifyEmail'));
});

it('dispatches OrganizationRegistered with the organisation and admin ids', function () {
    Event::fake([OrganizationRegistered::class]);

    $this->post($this->apexUrl('register'), signUp());

    $organization = Organization::query()->sole();
    $admin = $this->asTenant($organization, fn () => User::query()->sole());

    Event::assertDispatchedTimes(OrganizationRegistered::class, 1);
    Event::assertDispatched(OrganizationRegistered::class, fn (OrganizationRegistered $event): bool => $event->organizationId === $organization->id
        && $event->adminUserId === $admin->id);
});

it('creates nothing and dispatches nothing when the subdomain is taken between validation and insert', function () {
    Event::fake([OrganizationRegistered::class]);
    $this->createOrganization(['subdomain' => 'acme']);

    $input = signUp();
    unset($input['password_confirmation']);

    expect(fn () => app(RegisterOrganization::class)->handle($input, Locale::English))
        ->toThrow(ValidationException::class);

    expect(Organization::query()->count())->toBe(1)->and(allUsers())->toBe(0);
    Event::assertNotDispatched(OrganizationRegistered::class);
    Notification::assertNothingSent();
});

it('rejects a 4th successful registration from one IP within the hour with 429 and creates nothing', function () {
    foreach (['acme', 'beta', 'gamma'] as $subdomain) {
        $this->post($this->apexUrl('register'), signUp(['subdomain' => $subdomain]))->assertSessionHasNoErrors();
    }

    $this->post($this->apexUrl('register'), signUp(['subdomain' => 'delta']))->assertStatus(429);

    expect(Organization::query()->count())->toBe(3)
        ->and(Organization::query()->where('subdomain', 'delta')->exists())->toBeFalse()
        ->and(allUsers())->toBe(3);

    // Another IP is not affected; after an hour the IP may register again.
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->post($this->apexUrl('register'), signUp(['subdomain' => 'delta']))
        ->assertSessionHasNoErrors();

    $this->travel(61)->minutes();

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->post($this->apexUrl('register'), signUp(['subdomain' => 'epsilon']))
        ->assertSessionHasNoErrors();

    expect(Organization::query()->count())->toBe(5);
});

it('does not count registrations rejected by validation', function () {
    $this->createOrganization(['subdomain' => 'acme']);

    foreach (range(1, 5) as $attempt) {
        $this->post($this->apexUrl('register'), signUp())->assertSessionHasErrors('subdomain');
    }

    $this->post($this->apexUrl('register'), signUp(['subdomain' => 'beta']))->assertSessionHasNoErrors();

    expect(Organization::query()->where('subdomain', 'beta')->exists())->toBeTrue();
});

it('lets a registered org admin manage only users of their own organisation (OWN-001)', function () {
    $this->post($this->apexUrl('register'), signUp());
    $acme = Organization::query()->sole();
    $admin = $this->asTenant($acme, fn () => User::query()->sole());

    $other = $this->createOrganization(['subdomain' => 'beta']);
    $stranger = User::factory()->for($other, 'organization')->student()->create(['name' => 'Stranger']);

    expect($admin->can('view', $stranger))->toBeFalse()
        ->and($admin->can('update', $stranger))->toBeFalse()
        ->and($admin->can('delete', $stranger))->toBeFalse();

    $stranger->refresh();
    expect($stranger->name)->toBe('Stranger');
});

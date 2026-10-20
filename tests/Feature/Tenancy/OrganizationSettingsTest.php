<?php

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Enums\GradingScale;
use App\Modules\Tenancy\Enums\Locale;
use App\Modules\Tenancy\Events\OrganizationSettingsChanged;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-033 — Configure organisation (DATA-011, ARCH-008): settings form,
| authorisation, validation, logo, shared prop, default locale and the
| OrganizationSettingsChanged event.
*/

uses(RefreshDatabase::class);

const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test', 'tenancy.logo_disk' => 'logos']);
    Storage::fake('logos');

    $this->acme = $this->createOrganization([
        'name' => 'Acme',
        'subdomain' => 'acme',
        'accent_color' => null,
        'leaderboard_enabled' => true,
        'default_locale' => Locale::English,
    ]);
    $this->beta = $this->createOrganization(['subdomain' => 'beta']);

    $acmeUsers = User::factory()->for($this->acme, 'organization');
    $this->admin = $acmeUsers->orgAdmin()->create();
    $this->teacher = $acmeUsers->teacher()->create();
    $this->student = $acmeUsers->student()->create();

    $this->betaAdmin = User::factory()->for($this->beta, 'organization')->orgAdmin()->create();
});

/**
 * A complete valid form of the organisation, with overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function settingsForm(Organization $organization, array $overrides = []): array
{
    return [
        'accent_color' => $organization->accent_color ?? '',
        'student_term_el' => $organization->student_term_el,
        'student_term_en' => $organization->student_term_en,
        'teacher_term_el' => $organization->teacher_term_el,
        'teacher_term_en' => $organization->teacher_term_en,
        'grading_scale' => $organization->grading_scale->value,
        'leaderboard_enabled' => $organization->leaderboard_enabled ? '1' : '0',
        'default_locale' => $organization->default_locale->value,
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function organizationRow(Organization $organization): array
{
    return Organization::query()->findOrFail($organization->getKey())->getAttributes();
}

function pngLogo(string $name = 'logo.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, (string) base64_decode(PNG_1X1));
}

/**
 * @param  array<string, mixed>  $form
 */
function saveSettings(object $test, User $user, Organization $organization, array $form): mixed
{
    $url = (fn (): string => $this->tenantUrl($organization, 'organization/settings'))->call($test);

    return $test->actingAs($user)->from($url)->post($url, $form);
}

describe('saving settings', function () {
    it('saves accent colour and leaderboard of the org admin\'s organisation and shares them', function () {
        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme, [
            'accent_color' => '#1A2B3C',
            'leaderboard_enabled' => '0',
        ]))->assertRedirect($this->tenantUrl($this->acme, 'organization/settings'))
            ->assertSessionHasNoErrors();

        $acme = $this->acme->fresh();
        expect($acme->accent_color)->toBe('#1A2B3C')
            ->and($acme->leaderboard_enabled)->toBeFalse();

        $this->actingAs($this->admin)
            ->get($this->tenantUrl($this->acme, 'dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('organization.name', 'Acme')
                ->where('organization.accent_color', '#1A2B3C')
                ->where('organization.leaderboard_enabled', false)
                ->where('organization.grading_scale', $acme->grading_scale->value)
                ->where('organization.logo_url', null)
                ->has('organization.student_term')
                ->has('organization.teacher_term'));
    });

    it('saves terminology, grading scale and default language', function () {
        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme, [
            'student_term_el' => 'Σπουδαστής',
            'student_term_en' => 'Learner',
            'teacher_term_el' => 'Εκπαιδευτής',
            'teacher_term_en' => 'Trainer',
            'grading_scale' => GradingScale::Scale0To100->value,
            'default_locale' => Locale::Greek->value,
        ]))->assertSessionHasNoErrors();

        $acme = $this->acme->fresh();
        expect($acme->only(['student_term_el', 'student_term_en', 'teacher_term_el', 'teacher_term_en']))->toBe([
            'student_term_el' => 'Σπουδαστής',
            'student_term_en' => 'Learner',
            'teacher_term_el' => 'Εκπαιδευτής',
            'teacher_term_en' => 'Trainer',
        ])->and($acme->grading_scale)->toBe(GradingScale::Scale0To100)
            ->and($acme->default_locale)->toBe(Locale::Greek);
    });

    it('shares the terms in the current locale', function () {
        $this->acme->forceFill(['student_term_el' => 'Σπουδαστής', 'student_term_en' => 'Learner', 'teacher_term_el' => 'Εκπαιδευτής', 'teacher_term_en' => 'Trainer'])->save();

        $this->admin->forceFill(['locale' => Locale::Greek])->save();
        $this->actingAs($this->admin)->get($this->tenantUrl($this->acme, 'dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('organization.student_term', 'Σπουδαστής')
                ->where('organization.teacher_term', 'Εκπαιδευτής'));

        $this->admin->forceFill(['locale' => Locale::English])->save();
        $this->actingAs($this->admin)->get($this->tenantUrl($this->acme, 'dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('organization.student_term', 'Learner')
                ->where('organization.teacher_term', 'Trainer'));
    });

    it('shows the settings form with the subdomain to the org admin', function () {
        $this->actingAs($this->admin)
            ->get($this->tenantUrl($this->acme, 'organization/settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenancy/OrganizationSettings')
                ->where('settings.subdomain', 'acme')
                ->where('settings.accent_color', null)
                ->where('gradingScales', ['scale_0_10', 'scale_0_100', 'points_pass_mark'])
                ->where('locales', ['el', 'en']));
    });

    it('does not change the subdomain when another one is submitted', function () {
        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme, [
            'subdomain' => 'hijacked',
            'accent_color' => '#123456',
        ]))->assertSessionHasNoErrors();

        $acme = $this->acme->fresh();
        expect($acme->subdomain)->toBe('acme')
            ->and($acme->accent_color)->toBe('#123456')
            ->and(Organization::query()->where('subdomain', 'hijacked')->exists())->toBeFalse();
    });
});

describe('authorisation (ARCH-008)', function () {
    it('refuses teachers and students with 403 when they view or update the settings', function (string $who) {
        $user = $this->{$who};
        $before = organizationRow($this->acme);

        $this->actingAs($user)
            ->get($this->tenantUrl($this->acme, 'organization/settings'))
            ->assertForbidden();

        saveSettings($this, $user, $this->acme, settingsForm($this->acme, ['accent_color' => '#1A2B3C', 'leaderboard_enabled' => '0']))
            ->assertForbidden();

        expect(organizationRow($this->acme))->toBe($before);
    })->with(['teacher', 'student']);

    it('refuses an org admin of another organisation and leaves acme unchanged', function () {
        Event::fake([OrganizationSettingsChanged::class]);
        $before = organizationRow($this->acme);

        $this->actingAs($this->betaAdmin)
            ->get($this->tenantUrl($this->acme, 'organization/settings'))
            ->assertForbidden();

        saveSettings($this, $this->betaAdmin, $this->acme, settingsForm($this->acme, [
            'accent_color' => '#1A2B3C',
            'leaderboard_enabled' => '0',
            'logo' => pngLogo(),
        ]))->assertForbidden();

        expect(organizationRow($this->acme))->toBe($before)
            ->and(Storage::disk('logos')->allFiles())->toBe([]);
        Event::assertNotDispatched(OrganizationSettingsChanged::class);
    });

    it('sends guests to the login page', function () {
        $this->get($this->tenantUrl($this->acme, 'organization/settings'))
            ->assertRedirect($this->tenantUrl($this->acme, 'login'));
    });

    it('has no settings outside an organisation', function () {
        $this->actingAs($this->admin)
            ->get($this->apexUrl('organization/settings'))
            ->assertNotFound();
    });

    it('allows only an administering member of the same organisation in the policy', function () {
        expect($this->admin->can('update', $this->acme))->toBeTrue()
            ->and($this->teacher->can('update', $this->acme))->toBeFalse()
            ->and($this->student->can('update', $this->acme))->toBeFalse()
            ->and($this->betaAdmin->can('update', $this->acme))->toBeFalse()
            ->and($this->admin->can('update', $this->beta))->toBeFalse();
    });

    it('refuses a user that does not implement OrganizationMember', function () {
        $stranger = new GenericUser(['id' => 'x']);

        expect(Gate::forUser($stranger)->allows('update', $this->acme))->toBeFalse();
    });
});

describe('validation', function () {
    it('rejects an accent colour that is not #RRGGBB and saves nothing', function (string $color) {
        Event::fake([OrganizationSettingsChanged::class]);
        $before = organizationRow($this->acme);

        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme, [
            'accent_color' => $color,
            'leaderboard_enabled' => '0',
            'student_term_en' => 'Learner',
        ]))->assertSessionHasErrors(['accent_color' => __('organization.errors.accent_color')]);

        expect(organizationRow($this->acme))->toBe($before);
        Event::assertNotDispatched(OrganizationSettingsChanged::class);
    })->with(['blue', '#12345', '#1234567', '123456', '#GGGGGG']);

    it('accepts an empty accent colour and clears it', function () {
        $this->acme->forceFill(['accent_color' => '#ABCDEF'])->save();

        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme, ['accent_color' => '']))
            ->assertSessionHasNoErrors();

        expect($this->acme->fresh()->accent_color)->toBeNull();
    });

    it('requires the terms, a known grading scale and a known language', function () {
        $before = organizationRow($this->acme);

        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme, [
            'student_term_el' => '',
            'teacher_term_en' => str_repeat('x', 256),
            'grading_scale' => 'letters',
            'default_locale' => 'de',
            'leaderboard_enabled' => 'maybe',
        ]))->assertSessionHasErrors(['student_term_el', 'teacher_term_en', 'grading_scale', 'default_locale', 'leaderboard_enabled']);

        expect(organizationRow($this->acme))->toBe($before);
    });

    it('rejects a logo that is not png, jpg or svg, or larger than 2 MB, and saves nothing', function (UploadedFile $file) {
        $before = organizationRow($this->acme);

        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme, [
            'accent_color' => '#1A2B3C',
            'logo' => $file,
        ]))->assertSessionHasErrors('logo');

        expect(organizationRow($this->acme))->toBe($before)
            ->and(Storage::disk('logos')->allFiles())->toBe([]);
    })->with([
        'gif' => fn () => UploadedFile::fake()->createWithContent('logo.gif', "GIF89a\x01\x00\x01\x00\x00\x00\x00;"),
        'pdf' => fn () => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
        'html renamed svg' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<html><script>alert(1)</script></html>'),
        'svg with doctype' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x "y">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>'),
        'png over 2 MB' => fn () => UploadedFile::fake()->createWithContent('logo.png', base64_decode(PNG_1X1).str_repeat("\0", 2 * 1024 * 1024 + 1)),
    ]);
});

describe('logo', function () {
    it('stores an uploaded png on the private disk and serves it through the tenant route', function () {
        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme, ['logo' => pngLogo()]))
            ->assertSessionHasNoErrors();

        $path = $this->acme->fresh()->logo_path;
        expect($path)->toStartWith('organizations/'.$this->acme->getKey().'/')->toEndWith('.png');
        Storage::disk('logos')->assertExists($path);

        // The login page completes its own organization prop with the logo.
        auth()->logout();
        $this->get($this->tenantUrl($this->acme, 'login'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('organization.name', 'Acme')
                ->where('organization.logo_url', fn (string $url) => str_starts_with($url, '/organization/logo?v=')));

        $response = $this->get($this->tenantUrl($this->acme, 'organization/logo'));
        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        expect($response->streamedContent())->toBe(base64_decode(PNG_1X1));
    });

    it('serves only the current organisation\'s logo', function () {
        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme, ['logo' => pngLogo()]));

        $this->get($this->tenantUrl($this->beta, 'organization/logo'))->assertNotFound();
        $this->get($this->apexUrl('organization/logo'))->assertNotFound();
    });

    it('sanitises an svg logo', function () {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10" onload="alert(1)">'
            .'<script>alert(2)</script><foreignObject><p>x</p></foreignObject>'
            .'<a href="javascript:alert(3)"><rect width="10" height="10"/></a>'
            .'<image xlink:href="https://evil.test/x.png"/>'
            .'<circle cx="5" cy="5" r="4" fill="url(#g)" style="fill:url(https://evil.test/x)"/>'
            .'<use xlink:href="#c"/><path d="M0 0L10 10" fill="#1A2B3C"/></svg>';

        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme, [
            'logo' => UploadedFile::fake()->createWithContent('logo.svg', $svg),
        ]))->assertSessionHasNoErrors();

        $path = $this->acme->fresh()->logo_path;
        expect($path)->toEndWith('.svg');

        $stored = Storage::disk('logos')->get($path);
        expect($stored)
            ->not->toContain('onload')
            ->not->toContain('script')
            ->not->toContain('foreignObject')
            ->not->toContain('javascript')
            ->not->toContain('evil.test')
            ->not->toContain('<a ')
            ->toContain('<path d="M0 0L10 10" fill="#1A2B3C"/>')
            ->toContain('fill="url(#g)"')
            ->toContain('xlink:href="#c"');

        $this->get($this->tenantUrl($this->acme, 'organization/logo'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    });

    it('replaces the logo and deletes the old file', function () {
        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme, ['logo' => pngLogo()]));
        $old = $this->acme->fresh()->logo_path;

        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme->fresh(), ['logo' => pngLogo('new.png')]));
        $new = $this->acme->fresh()->logo_path;

        expect($new)->not->toBe($old);
        Storage::disk('logos')->assertMissing($old);
        Storage::disk('logos')->assertExists($new);
    });

    it('removes the logo; the login page and app shell then show the app logo', function () {
        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme, ['logo' => pngLogo()]));
        $path = $this->acme->fresh()->logo_path;
        expect($path)->not->toBeNull();

        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme->fresh(), ['remove_logo' => '1']))
            ->assertSessionHasNoErrors();

        expect($this->acme->fresh()->logo_path)->toBeNull();
        Storage::disk('logos')->assertMissing($path);

        // The login page (guest) and the app shell (signed in) get no logo
        // URL, so they render the app logo.
        auth()->logout();
        $this->get($this->tenantUrl($this->acme, 'login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->where('organization.name', 'Acme')
                ->where('organization.logo_url', null));

        $this->actingAs($this->teacher)
            ->get($this->tenantUrl($this->acme, 'dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('organization.logo_url', null));

        $this->get($this->tenantUrl($this->acme, 'organization/logo'))->assertNotFound();
    });
});

describe('default language', function () {
    it('shows the login page in Greek to guests when the default language is el', function () {
        $this->acme->forceFill(['default_locale' => Locale::Greek])->save();
        config(['app.locale' => 'en']);

        $this->get($this->tenantUrl($this->acme, 'login'))
            ->assertOk()
            ->assertSee('<html lang="el"', false);
    });

    it('uses the default language for a user without a locale and the user\'s own otherwise', function () {
        $this->acme->forceFill(['default_locale' => Locale::Greek])->save();
        config(['app.locale' => 'en']);

        $this->teacher->forceFill(['locale' => null])->save();
        $this->actingAs($this->teacher)
            ->get($this->tenantUrl($this->acme, 'dashboard'))
            ->assertSee('<html lang="el"', false);

        $this->student->forceFill(['locale' => Locale::English])->save();
        $this->actingAs($this->student)
            ->get($this->tenantUrl($this->acme, 'dashboard'))
            ->assertSee('<html lang="en"', false);
    });

    it('keeps the application locale outside an organisation', function () {
        config(['app.locale' => 'en']);

        $this->get($this->apexUrl('/'))->assertSee('<html lang="en"', false);
    });
});

describe('OrganizationSettingsChanged (DATA-011)', function () {
    it('dispatches one event with both changed fields as {from, to}', function () {
        Event::fake([OrganizationSettingsChanged::class]);

        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme, [
            'accent_color' => '#1A2B3C',
            'leaderboard_enabled' => '0',
        ]))->assertSessionHasNoErrors();

        Event::assertDispatchedTimes(OrganizationSettingsChanged::class, 1);
        Event::assertDispatched(OrganizationSettingsChanged::class, fn (OrganizationSettingsChanged $event) => $event->organizationId === $this->acme->getKey()
            && $event->actorId === $this->admin->getKey()
            && $event->changes === [
                'accent_color' => ['from' => null, 'to' => '#1A2B3C'],
                'leaderboard_enabled' => ['from' => true, 'to' => false],
            ]);
    });

    it('dispatches after the save has committed', function () {
        $seen = null;
        $level = DB::transactionLevel();

        Event::listen(OrganizationSettingsChanged::class, function () use (&$seen, $level) {
            $seen = [
                'level' => DB::transactionLevel(),
                'accent_color' => DB::table('organizations')->where('id', $this->acme->getKey())->value('accent_color'),
            ];
            expect($seen['level'])->toBe($level);
        });

        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme, ['accent_color' => '#1A2B3C']));

        expect($seen)->toBe(['level' => $level, 'accent_color' => '#1A2B3C']);
    });

    it('puts the logo path, never the file contents, in the changes', function () {
        Event::fake([OrganizationSettingsChanged::class]);

        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme, ['logo' => pngLogo()]));

        $path = $this->acme->fresh()->logo_path;
        Event::assertDispatched(OrganizationSettingsChanged::class, fn (OrganizationSettingsChanged $event) => $event->changes === [
            'logo_path' => ['from' => null, 'to' => $path],
        ]);
    });

    it('dispatches nothing when nothing changed', function () {
        Event::fake([OrganizationSettingsChanged::class]);

        saveSettings($this, $this->admin, $this->acme, settingsForm($this->acme))->assertSessionHasNoErrors();

        Event::assertNotDispatched(OrganizationSettingsChanged::class);
    });
});

<?php

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Enums\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/*
| Browser flow of UC-033: the org admin configures the organisation and the
| settings apply at once (DATA-011): accent colour as primary colour, logo in
| the app shell and on the login page, default language for guests.
|
| The browser test server always answers as http://127.0.0.1:<port>, so the
| organisation "127" on the apex domain "0.0.1" stands in for a subdomain.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => '0.0.1', 'app.locale' => 'en', 'tenancy.logo_disk' => 'logos']);
    Storage::fake('logos');

    $this->organization = $this->createOrganization(['subdomain' => '127', 'name' => 'Acme School', 'leaderboard_enabled' => true]);
});

it('lets an org admin set the accent colour and leaderboard, applied at once', function () {
    $admin = User::factory()->for($this->organization, 'organization')->orgAdmin()->create();
    $this->actingAs($admin);

    $page = visit('/organization/settings')
        ->assertSee('Organisation settings')
        ->assertValue('#subdomain', '127')
        ->assertPresent('[data-test="app-logo"]')
        ->fill('accent_color', '#1A2B3C')
        ->click('@leaderboard-enabled')
        ->click('@organization-save')
        ->assertSee('Settings saved.')
        ->assertScript("getComputedStyle(document.documentElement).getPropertyValue('--primary').trim()", '#1A2B3C')
        ->assertNoJavaScriptErrors();

    $organization = $this->organization->fresh();
    expect($organization->accent_color)->toBe('#1A2B3C')
        ->and($organization->leaderboard_enabled)->toBeFalse();

    $page->fill('accent_color', 'blue')
        ->click('@organization-save')
        ->assertSee('The accent colour must have the format #RRGGBB');

    expect($this->organization->fresh()->accent_color)->toBe('#1A2B3C');
});

function giveLogo(object $test): void
{
    Storage::disk('logos')->put('organizations/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>');
    $test->organization->forceFill(['logo_path' => 'organizations/logo.svg'])->save();
}

it('shows the organisation logo in the app shell', function () {
    giveLogo($this);
    $this->actingAs(User::factory()->for($this->organization, 'organization')->teacher()->create());

    visit('/dashboard')
        ->assertPresent('[data-test="organization-logo"]')
        ->assertNotPresent('[data-test="app-logo"]')
        ->assertNoJavaScriptErrors();
});

it('shows the organisation logo on the login page, in its default language', function () {
    $this->organization->forceFill(['default_locale' => Locale::Greek])->save();
    giveLogo($this);

    visit('/login')
        ->assertSee('Συνδεθείτε στον λογαριασμό σας')
        ->assertPresent('[data-test="organization-logo"]')
        ->assertNotPresent('[data-test="app-logo"]')
        ->assertNoJavaScriptErrors();
});

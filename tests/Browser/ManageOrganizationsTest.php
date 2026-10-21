<?php

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Database\Factories\PlatformAdminFactory;
use App\Modules\Platform\Models\PlatformAdmin;
use App\Modules\Tenancy\Enums\OrganizationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;

/*
| Browser flow of P8-T2: a platform admin logs in on the apex domain, sees
| the platform dashboard (UC-035), opens the organisation list and suspends,
| then reactivates, an organisation (UC-036).
|
| The browser test server answers as http://127.0.0.1:<port>: 127.0.0.1 is
| the apex domain here.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => '127.0.0.1', 'app.locale' => 'en']);
});

it('shows the dashboard and suspends and reactivates an organisation', function () {
    $secret = (new Google2FA)->generateSecretKey();
    PlatformAdmin::factory()->withTwoFactor($secret)->create(['email' => 'root@uplift.test', 'name' => 'Root Admin']);

    $acme = $this->createOrganization(['subdomain' => 'acme', 'name' => 'Acme School']);
    $beta = $this->createOrganization(['subdomain' => 'beta', 'name' => 'Beta University']);
    User::factory()->for($acme, 'organization')->count(2)->create();
    User::factory()->for($beta, 'organization')->teacher()->create();

    $page = visit('/admin/login')
        ->fill('email', 'root@uplift.test')
        ->fill('password', PlatformAdminFactory::PASSWORD)
        ->click('@platform-login-button')
        ->type('#code', (new Google2FA)->getCurrentOtp($secret))
        ->click('@platform-challenge-submit')
        ->assertSee('Signed in as Root Admin')
        ->assertSeeIn('@metric-organizations-total', '2')
        ->assertSeeIn('@metric-users-total', '3')
        ->assertSeeIn('@metric-users-student', '2')
        ->assertSee('Acme School')
        ->click('@manage-organizations')
        ->assertSee('Organisations')
        ->assertSee('Beta University');

    $page->click('@organization-suspend')
        ->assertSee('Suspend Acme School?')
        ->click('@organization-status-confirm')
        ->assertSee('The organisation was suspended.')
        ->assertSee('Suspended')
        ->assertVisible('@organization-reactivate');

    expect($acme->fresh()->status)->toBe(OrganizationStatus::Suspended)
        ->and($beta->fresh()->status)->toBe(OrganizationStatus::Active);

    $page->click('@organization-reactivate')
        ->assertSee('Reactivate Acme School?')
        ->click('@organization-status-confirm')
        ->assertSee('The organisation was reactivated.')
        ->assertNoJavaScriptErrors();

    expect($acme->fresh()->status)->toBe(OrganizationStatus::Active);
});

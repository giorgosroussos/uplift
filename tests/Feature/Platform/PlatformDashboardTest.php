<?php

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\PlatformAdmin;
use App\Modules\Tenancy\Enums\OrganizationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;

/*
| P8-T2 — UC-035: the platform dashboard shows all organisations' key
| metrics (organisations by status, users by role) and the newest
| organisations, across tenants and without personal data (SEC-006).
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->admin = PlatformAdmin::factory()->withTwoFactor((new Google2FA)->generateSecretKey())->create(['name' => 'Root Admin']);
});

it('shows organisations by status and users by role across all organisations', function () {
    Carbon::setTestNow('2026-10-01 10:00:00');
    $acme = $this->createOrganization(['subdomain' => 'acme', 'name' => 'Acme School']);
    Carbon::setTestNow('2026-10-02 10:00:00');
    $beta = $this->createOrganization(['subdomain' => 'beta', 'name' => 'Beta University']);
    Carbon::setTestNow('2026-10-03 10:00:00');
    $gamma = $this->createOrganization(['subdomain' => 'gamma', 'name' => 'Gamma Tutoring']);
    $gamma->transitionTo(OrganizationStatus::Suspended);
    Carbon::setTestNow();

    User::factory()->for($acme, 'organization')->count(2)->create();
    User::factory()->for($acme, 'organization')->teacher()->create(['email' => 'teacher@acme.test']);
    User::factory()->for($acme, 'organization')->orgAdmin()->create();
    User::factory()->for($beta, 'organization')->create(['is_active' => false]);
    User::factory()->for($gamma, 'organization')->teacher()->create();

    $this->actingAs($this->admin, 'platform')
        ->get($this->apexUrl('admin'))
        ->assertOk()
        ->assertDontSee('teacher@acme.test')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Dashboard')
            ->where('admin.name', 'Root Admin')
            ->where('metrics.organizations', ['total' => 3, 'active' => 2, 'suspended' => 1])
            ->where('metrics.users', ['total' => 6, 'active' => 5, 'student' => 3, 'teacher' => 2, 'org_admin' => 1])
            ->has('metrics.recent_organizations', 3)
            ->where('metrics.recent_organizations.0.subdomain', 'gamma')
            ->where('metrics.recent_organizations.0.status', 'suspended')
            ->where('metrics.recent_organizations.2.subdomain', 'acme'));
});

it('shows zeros on an empty platform', function () {
    $this->actingAs($this->admin, 'platform')
        ->get($this->apexUrl('admin'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.organizations', ['total' => 0, 'active' => 0, 'suspended' => 0])
            ->where('metrics.users', ['total' => 0, 'active' => 0, 'student' => 0, 'teacher' => 0, 'org_admin' => 0])
            ->has('metrics.recent_organizations', 0));
});

it('limits the newest organisations to five', function () {
    foreach (range(1, 7) as $i) {
        $this->createOrganization(['subdomain' => "org{$i}"]);
    }

    $this->actingAs($this->admin, 'platform')
        ->get($this->apexUrl('admin'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.organizations.total', 7)
            ->has('metrics.recent_organizations', 5));
});

it('is only for platform admins on the apex domain', function () {
    $acme = $this->createOrganization(['subdomain' => 'acme']);
    $orgAdmin = User::factory()->for($acme, 'organization')->orgAdmin()->create();

    $this->get($this->apexUrl('admin'))->assertRedirect($this->apexUrl('admin/login'));

    $this->actingAs($orgAdmin)->get($this->apexUrl('admin'))->assertRedirect($this->apexUrl('admin/login'));

    $this->actingAs($this->admin, 'platform')->get($this->tenantUrl($acme, 'admin'))->assertNotFound();
});

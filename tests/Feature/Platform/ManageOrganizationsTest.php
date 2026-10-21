<?php

use App\Modules\Identity\Database\Factories\UserFactory;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Actions\ReactivateOrganization;
use App\Modules\Platform\Actions\SuspendOrganization;
use App\Modules\Platform\Events\OrganizationReactivated;
use App\Modules\Platform\Events\OrganizationSuspended;
use App\Modules\Platform\Models\PlatformAdmin;
use App\Modules\Tenancy\Enums\OrganizationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;

/*
| P8-T2 — UC-036: a platform admin lists all organisations, sees their status
| and suspends or reactivates them through the state model of
| ENT-Organization (`active -> suspended`, `suspended -> active`). Only
| platform admins with confirmed MFA on the apex domain get there (SEC-006,
| SEC-011). Each change dispatches an event for the audit (SEC-010).
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->admin = PlatformAdmin::factory()->withTwoFactor((new Google2FA)->generateSecretKey())->create();

    $this->acme = $this->createOrganization(['subdomain' => 'acme', 'name' => 'Acme School']);
    $this->beta = $this->createOrganization(['subdomain' => 'beta', 'name' => 'Beta University']);
    $this->gamma = $this->createOrganization(['subdomain' => 'gamma', 'name' => 'Gamma Tutoring']);
    $this->gamma->transitionTo(OrganizationStatus::Suspended);

    $this->teacher = User::factory()->for($this->acme, 'organization')->teacher()->create(['email' => 'teacher@acme.test']);
    User::factory()->for($this->acme, 'organization')->orgAdmin()->create();
    User::factory()->for($this->beta, 'organization')->count(3)->create();
});

describe('listing organisations', function () {
    it('lists every organisation with its status and number of users', function () {
        $this->actingAs($this->admin, 'platform')
            ->get($this->apexUrl('admin/organizations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Organizations')
                ->where('organizations.total', 3)
                ->where('organizations.data.0.name', 'Acme School')
                ->where('organizations.data.0.subdomain', 'acme')
                ->where('organizations.data.0.status', 'active')
                ->where('organizations.data.0.users_count', 2)
                ->where('organizations.data.1.name', 'Beta University')
                ->where('organizations.data.1.users_count', 3)
                ->where('organizations.data.2.name', 'Gamma Tutoring')
                ->where('organizations.data.2.status', 'suspended')
                ->where('organizations.data.2.users_count', 0)
                ->where('statuses', ['active', 'suspended']));
    });

    it('filters by status and searches by name or subdomain', function () {
        $this->actingAs($this->admin, 'platform');

        $this->get($this->apexUrl('admin/organizations?status=suspended'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('organizations.total', 1)
                ->where('organizations.data.0.subdomain', 'gamma')
                ->where('filters.status', 'suspended'));

        $this->get($this->apexUrl('admin/organizations?search=UNIVERS'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('organizations.total', 1)
                ->where('organizations.data.0.subdomain', 'beta'));

        $this->get($this->apexUrl('admin/organizations?search=acm'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('organizations.total', 1)
                ->where('organizations.data.0.subdomain', 'acme'));
    });

    it('treats LIKE wildcards in the search literally', function () {
        $this->actingAs($this->admin, 'platform')
            ->get($this->apexUrl('admin/organizations?search=%25'))
            ->assertInertia(fn (Assert $page) => $page->where('organizations.total', 0));
    });

    it('rejects an unknown status filter', function () {
        $this->actingAs($this->admin, 'platform')
            ->get($this->apexUrl('admin/organizations?status=deleted'))
            ->assertSessionHasErrors('status');
    });

    it('never exposes personal data of organisation users', function () {
        $this->actingAs($this->admin, 'platform')
            ->get($this->apexUrl('admin/organizations'))
            ->assertDontSee('teacher@acme.test')
            ->assertDontSee($this->teacher->name);
    });
});

describe('suspending and reactivating', function () {
    it('suspends an active organisation and dispatches OrganizationSuspended', function () {
        Event::fake([OrganizationSuspended::class, OrganizationReactivated::class]);

        $this->actingAs($this->admin, 'platform')
            ->from($this->apexUrl('admin/organizations'))
            ->post($this->apexUrl("admin/organizations/{$this->acme->id}/suspend"))
            ->assertRedirect($this->apexUrl('admin/organizations'))
            ->assertSessionHasNoErrors();

        expect($this->acme->fresh()->status)->toBe(OrganizationStatus::Suspended);
        Event::assertDispatchedTimes(OrganizationSuspended::class, 1);
        Event::assertDispatched(OrganizationSuspended::class, fn (OrganizationSuspended $event) => $event->organizationId === $this->acme->id
            && $event->platformAdminId === $this->admin->id);
        Event::assertNotDispatched(OrganizationReactivated::class);
    });

    it('reactivates a suspended organisation and dispatches OrganizationReactivated', function () {
        Event::fake([OrganizationSuspended::class, OrganizationReactivated::class]);

        $this->actingAs($this->admin, 'platform')
            ->post($this->apexUrl("admin/organizations/{$this->gamma->id}/reactivate"))
            ->assertSessionHasNoErrors();

        expect($this->gamma->fresh()->status)->toBe(OrganizationStatus::Active);
        Event::assertDispatched(OrganizationReactivated::class, fn (OrganizationReactivated $event) => $event->organizationId === $this->gamma->id
            && $event->platformAdminId === $this->admin->id);
        Event::assertNotDispatched(OrganizationSuspended::class);
    });

    it('rejects a transition that is not in the state model and leaves the status unchanged', function (string $organization, string $action, OrganizationStatus $status) {
        Event::fake([OrganizationSuspended::class, OrganizationReactivated::class]);
        $target = $this->{$organization};

        $this->actingAs($this->admin, 'platform')
            ->post($this->apexUrl("admin/organizations/{$target->id}/{$action}"))
            ->assertSessionHasErrors('organization');

        expect($target->fresh()->status)->toBe($status);
        Event::assertNotDispatched(OrganizationSuspended::class);
        Event::assertNotDispatched(OrganizationReactivated::class);
    })->with([
        'suspended -> suspended' => ['gamma', 'suspend', OrganizationStatus::Suspended],
        'active -> active' => ['acme', 'reactivate', OrganizationStatus::Active],
    ]);

    it('rejects the transition in the actions too, not only over HTTP', function () {
        expect(fn () => app(SuspendOrganization::class)->handle($this->admin, $this->gamma))
            ->toThrow(ValidationException::class)
            ->and(fn () => app(ReactivateOrganization::class)->handle($this->admin, $this->acme))
            ->toThrow(ValidationException::class);

        expect($this->gamma->fresh()->status)->toBe(OrganizationStatus::Suspended)
            ->and($this->acme->fresh()->status)->toBe(OrganizationStatus::Active);
    });

    it('answers 404 for an unknown organisation', function () {
        $this->actingAs($this->admin, 'platform')
            ->post($this->apexUrl('admin/organizations/0199a8c4-0000-7000-8000-000000000000/suspend'))
            ->assertNotFound();
    });

    it('stops the users of a suspended organisation from logging in, and lets them in again after reactivation', function () {
        $this->actingAs($this->admin, 'platform')
            ->post($this->apexUrl("admin/organizations/{$this->acme->id}/suspend"));

        auth('platform')->logout();

        $this->post($this->tenantUrl($this->acme, 'login'), ['email' => 'teacher@acme.test', 'password' => UserFactory::PASSWORD])
            ->assertSessionHasErrors('email');
        $this->assertGuest('web');

        $this->actingAs($this->admin, 'platform')
            ->post($this->apexUrl("admin/organizations/{$this->acme->id}/reactivate"));

        auth('platform')->logout();

        $this->post($this->tenantUrl($this->acme, 'login'), ['email' => 'teacher@acme.test', 'password' => UserFactory::PASSWORD])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($this->teacher->fresh(), 'web');
    });
});

describe('access', function () {
    it('sends a guest to the platform login', function (string $method, string $path) {
        $url = $this->apexUrl(str_replace('{id}', $this->acme->id, $path));
        $response = $method === 'GET' ? $this->get($url) : $this->post($url);

        $response->assertRedirect($this->apexUrl('admin/login'));
        expect($this->acme->fresh()->status)->toBe(OrganizationStatus::Active);
    })->with([
        ['GET', 'admin/organizations'],
        ['POST', 'admin/organizations/{id}/suspend'],
    ]);

    it('does not let an organisation admin manage organisations', function () {
        $orgAdmin = User::factory()->for($this->acme, 'organization')->orgAdmin()->create();

        $this->actingAs($orgAdmin)
            ->post($this->apexUrl("admin/organizations/{$this->beta->id}/suspend"))
            ->assertRedirect($this->apexUrl('admin/login'));

        $this->actingAs($orgAdmin)
            ->post($this->tenantUrl($this->acme, "admin/organizations/{$this->beta->id}/suspend"))
            ->assertNotFound();

        expect($this->beta->fresh()->status)->toBe(OrganizationStatus::Active);
    });

    it('does not let a platform admin without confirmed MFA in', function () {
        $admin = PlatformAdmin::factory()->create();

        $this->actingAs($admin, 'platform')
            ->post($this->apexUrl("admin/organizations/{$this->acme->id}/suspend"))
            ->assertRedirect($this->apexUrl('admin/login'));

        expect($this->acme->fresh()->status)->toBe(OrganizationStatus::Active);
    });

    it('does not exist on an organisation subdomain', function () {
        $this->actingAs($this->admin, 'platform')
            ->get($this->tenantUrl($this->acme, 'admin/organizations'))
            ->assertNotFound();
    });
});

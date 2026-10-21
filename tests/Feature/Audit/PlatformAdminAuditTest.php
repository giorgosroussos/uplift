<?php

use App\Modules\Audit\Enums\ActorType;
use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\PlatformAdmin;
use App\Modules\Tenancy\Enums\OrganizationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;

/*
| P8-T3: audit entries for platform admin actions (SEC-010): suspending and
| reactivating an organisation (UC-036). Entries are written by the platform
| admin on the organisation and hold the status change only.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->admin = PlatformAdmin::factory()->withTwoFactor((new Google2FA)->generateSecretKey())->create();
    $this->active = $this->createOrganization(['subdomain' => 'acme']);
    $this->suspended = $this->createOrganization(['subdomain' => 'gamma']);
    $this->suspended->transitionTo(OrganizationStatus::Suspended);
});

it('writes one organization.suspended entry by the platform admin with the status from and to', function () {
    $this->actingAs($this->admin, 'platform')
        ->post($this->apexUrl("admin/organizations/{$this->active->id}/suspend"))
        ->assertSessionHasNoErrors();

    $entry = AuditEntry::query()->sole();

    expect($entry->action)->toBe('organization.suspended')
        ->and($entry->actor_type)->toBe(ActorType::PlatformAdmin)
        ->and($entry->actor_id)->toBe($this->admin->getKey())
        ->and($entry->subject_type)->toBe('organization')
        ->and($entry->subject_id)->toBe($this->active->getKey())
        ->and($entry->organization_id)->toBe($this->active->getKey())
        ->and($entry->changes)->toEqual(['status' => ['from' => 'active', 'to' => 'suspended']]);
});

it('writes one organization.reactivated entry by the platform admin with the status from and to', function () {
    $this->actingAs($this->admin, 'platform')
        ->post($this->apexUrl("admin/organizations/{$this->suspended->id}/reactivate"))
        ->assertSessionHasNoErrors();

    $entry = AuditEntry::query()->sole();

    expect($entry->action)->toBe('organization.reactivated')
        ->and($entry->actor_type)->toBe(ActorType::PlatformAdmin)
        ->and($entry->actor_id)->toBe($this->admin->getKey())
        ->and($entry->subject_type)->toBe('organization')
        ->and($entry->subject_id)->toBe($this->suspended->getKey())
        ->and($entry->organization_id)->toBe($this->suspended->getKey())
        ->and($entry->changes)->toEqual(['status' => ['from' => 'suspended', 'to' => 'active']]);
});

it('writes one entry each for a suspension followed by a reactivation, in order', function () {
    $this->actingAs($this->admin, 'platform');

    $this->post($this->apexUrl("admin/organizations/{$this->active->id}/suspend"))->assertSessionHasNoErrors();
    $this->travel(1)->seconds();
    $this->post($this->apexUrl("admin/organizations/{$this->active->id}/reactivate"))->assertSessionHasNoErrors();

    expect(AuditEntry::query()->orderBy('occurred_at')->pluck('action')->all())
        ->toBe(['organization.suspended', 'organization.reactivated']);
});

it('writes no entry when the transition is not in the state model', function (string $organization, string $action) {
    $target = $this->{$organization};

    $this->actingAs($this->admin, 'platform')
        ->post($this->apexUrl("admin/organizations/{$target->id}/{$action}"))
        ->assertSessionHasErrors('organization');

    expect(AuditEntry::query()->count())->toBe(0);
})->with([
    'suspended -> suspended' => ['suspended', 'suspend'],
    'active -> active' => ['active', 'reactivate'],
]);

it('writes no entry when a guest tries to suspend an organisation', function () {
    $this->post($this->apexUrl("admin/organizations/{$this->active->id}/suspend"))
        ->assertRedirect($this->apexUrl('admin/login'));

    expect(AuditEntry::query()->count())->toBe(0);
});

it('writes no entry when an organisation admin tries to suspend an organisation', function () {
    $orgAdmin = User::factory()->for($this->active, 'organization')->orgAdmin()->create();

    $this->actingAs($orgAdmin)
        ->post($this->apexUrl("admin/organizations/{$this->active->id}/suspend"))
        ->assertRedirect($this->apexUrl('admin/login'));

    expect(AuditEntry::query()->count())->toBe(0);
});

it('writes no entry when a platform admin without confirmed MFA tries to suspend an organisation', function () {
    $this->actingAs(PlatformAdmin::factory()->create(), 'platform')
        ->post($this->apexUrl("admin/organizations/{$this->active->id}/suspend"))
        ->assertRedirect($this->apexUrl('admin/login'));

    expect(AuditEntry::query()->count())->toBe(0);
});

it('stores no personal data of the platform admin in the entry', function () {
    $this->actingAs($this->admin, 'platform')
        ->post($this->apexUrl("admin/organizations/{$this->active->id}/suspend"));

    $raw = json_encode(AuditEntry::query()->sole()->getAttributes());

    expect($raw)->not->toContain($this->admin->email)
        ->and($raw)->not->toContain($this->admin->name);
});

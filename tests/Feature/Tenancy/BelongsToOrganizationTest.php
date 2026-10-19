<?php

use App\Modules\Tenancy\Exceptions\TenantMismatch;
use App\Modules\Tenancy\Support\OrganizationScope;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Tenancy\Fixtures\TenantOwnedThing;

uses(RefreshDatabase::class);

beforeEach(function () {
    TenantOwnedThing::createTable();
    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->beta = $this->createOrganization(['subdomain' => 'beta']);
});

it('passes the reusable tenant-isolation assertion', function () {
    $this->assertTenantIsolated(
        TenantOwnedThing::class,
        fn () => TenantOwnedThing::query()->create(['name' => fake()->word()]),
    );
});

it('fills organization_id from the current tenant', function () {
    $thing = $this->asTenant($this->acme, fn () => TenantOwnedThing::query()->create(['name' => 'a']));

    expect($thing->organization_id)->toBe($this->acme->id)
        ->and($thing->organization->is($this->acme))->toBeTrue();
});

it('only returns rows of the current tenant', function () {
    $this->asTenant($this->acme, fn () => TenantOwnedThing::query()->create(['name' => 'a']));
    $this->asTenant($this->beta, fn () => TenantOwnedThing::query()->create(['name' => 'b']));

    $this->actingAsTenant($this->acme);
    expect(TenantOwnedThing::query()->pluck('name')->all())->toBe(['a']);

    $this->actingAsTenant($this->beta);
    expect(TenantOwnedThing::query()->pluck('name')->all())->toBe(['b']);
});

it('returns nothing when no tenant is set', function () {
    $this->asTenant($this->acme, fn () => TenantOwnedThing::query()->create(['name' => 'a']));

    $this->withoutTenant();

    expect(TenantOwnedThing::query()->count())->toBe(0)
        ->and(TenantOwnedThing::withoutGlobalScope(OrganizationScope::class)->count())->toBe(1);
});

it('rejects creating a row for another tenant', function () {
    $this->actingAsTenant($this->acme);

    expect(fn () => TenantOwnedThing::query()->create(['name' => 'x', 'organization_id' => $this->beta->id]))
        ->toThrow(TenantMismatch::class);

    expect(TenantOwnedThing::withoutGlobalScope(OrganizationScope::class)->count())->toBe(0);
});

it('rejects moving a row to another tenant', function () {
    $thing = $this->asTenant($this->acme, fn () => TenantOwnedThing::query()->create(['name' => 'a']));

    $this->actingAsTenant($this->acme);
    $thing->organization_id = $this->beta->id;

    expect(fn () => $thing->save())->toThrow(TenantMismatch::class);
    expect(TenantOwnedThing::query()->find($thing->id)?->organization_id)->toBe($this->acme->id);
});

it('rejects creating a row without any organisation', function () {
    $this->withoutTenant();

    expect(fn () => TenantOwnedThing::query()->create(['name' => 'x']))->toThrow(TenantMismatch::class);
});

it('allows an explicit organisation when no tenant is set (e.g. registration on the apex domain)', function () {
    $this->withoutTenant();

    $thing = TenantOwnedThing::query()->create(['name' => 'x', 'organization_id' => $this->beta->id]);

    expect($thing->organization_id)->toBe($this->beta->id);
});

it('restores the previous tenant after running as another one', function () {
    $context = app(TenantContext::class);
    $context->set($this->acme);

    $seen = $context->run($this->beta, fn () => $context->id());

    expect($seen)->toBe($this->beta->id)
        ->and($context->id())->toBe($this->acme->id);
});

it('gives every request and job a fresh tenant context', function () {
    $context = app(TenantContext::class);
    $context->set($this->acme);

    app()->forgetScopedInstances();

    expect(app(TenantContext::class))->not->toBe($context)
        ->and(app(TenantContext::class)->check())->toBeFalse();
});

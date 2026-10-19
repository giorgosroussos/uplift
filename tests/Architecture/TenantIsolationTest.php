<?php

use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Tenancy\Fixtures\TenantOwnedThing;
use Tests\Support\Tenancy\Fixtures\UnscopedThing;
use Tests\Support\Tenancy\TenantIsolation;

/*
| Phase 1: a tenant-isolation test fails if any tenant-owned model lacks the
| organization_id global scope (DATA-007, SEC-004).
*/

uses(RefreshDatabase::class);

it('gives every tenant-owned model the organization_id global scope', function () {
    $missing = TenantIsolation::modelsMissingScope(TenantIsolation::applicationModels(), TenantIsolation::tenantOwnedTables());

    expect($missing)->toBe([], 'Tenant-owned models without BelongsToOrganization: '.implode(', ', $missing));
});

it('maps every tenant-owned table to a model', function () {
    $unmapped = TenantIsolation::tablesWithoutModel(TenantIsolation::applicationModels(), TenantIsolation::tenantOwnedTables());

    expect($unmapped)->toBe([], 'Tenant-owned tables without a model: '.implode(', ', $unmapped));
});

it('finds the application models', function () {
    expect(TenantIsolation::applicationModels())
        ->toContain(Organization::class);
});

it('reports a tenant-owned model that lacks the scope', function () {
    TenantOwnedThing::createTable();
    $tables = TenantIsolation::tenantOwnedTables();

    expect($tables)->toContain('tenancy_fixture_things')
        ->and(TenantIsolation::modelsMissingScope([UnscopedThing::class, TenantOwnedThing::class], $tables))
        ->toBe([UnscopedThing::class]);
});

it('reports a tenant-owned table without a model', function () {
    TenantOwnedThing::createTable();
    $tables = TenantIsolation::tenantOwnedTables();

    expect(TenantIsolation::tablesWithoutModel([], $tables))->toContain('tenancy_fixture_things')
        ->and(TenantIsolation::tablesWithoutModel([TenantOwnedThing::class], $tables))->not->toContain('tenancy_fixture_things');
});

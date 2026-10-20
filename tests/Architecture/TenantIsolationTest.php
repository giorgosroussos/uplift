<?php

use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\OrganizationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Tenancy\Fixtures\TenantOwnedThing;
use Tests\Support\Tenancy\Fixtures\UnscopedThing;
use Tests\Support\Tenancy\TenantIsolation;

/*
| Phase 1: a tenant-isolation test fails if any tenant-owned model lacks the
| organization_id global scope (DATA-007, SEC-004).
*/

uses(RefreshDatabase::class);

/*
| Models with an organization_id that the design exempts from the
| organisation global scope. Each entry needs a design reference.
*/
const TENANT_SCOPE_EXEMPT_MODELS = [
    // ENT-AuditEntry: organization_id is null for platform-level actions and
    // only platform admins read it (SEC-006, design v1.2.0, CR-P1-T5-1).
    AuditEntry::class,
];

it('gives every tenant-owned model the organization_id global scope', function () {
    $missing = array_values(array_diff(
        TenantIsolation::modelsMissingScope(TenantIsolation::applicationModels(), TenantIsolation::tenantOwnedTables()),
        TENANT_SCOPE_EXEMPT_MODELS,
    ));

    expect($missing)->toBe([], 'Tenant-owned models without BelongsToOrganization: '.implode(', ', $missing));
});

it('maps every tenant-owned table to a model', function () {
    $unmapped = TenantIsolation::tablesWithoutModel(TenantIsolation::applicationModels(), TenantIsolation::tenantOwnedTables());

    expect($unmapped)->toBe([], 'Tenant-owned tables without a model: '.implode(', ', $unmapped));
});

it('keeps the design-sanctioned exemptions to tenant-owned models without the scope', function (string $model) {
    expect(TenantIsolation::applicationModels())->toContain($model)
        ->and(TenantIsolation::tenantOwnedTables())->toContain((new $model)->getTable())
        ->and($model::hasGlobalScope(OrganizationScope::class))->toBeFalse();
})->with(TENANT_SCOPE_EXEMPT_MODELS);

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

<?php

namespace App\Modules\Tenancy\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits every query of a tenant-owned model to the current tenant.
 *
 * Fails closed: without a current tenant the query matches nothing. Code that
 * legitimately works across tenants (platform admin, maintenance) removes the
 * scope explicitly with withoutGlobalScope(OrganizationScope::class).
 *
 * @implements Scope<Model>
 */
final class OrganizationScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $organizationId = app(TenantContext::class)->id();

        if ($organizationId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('organization_id'), $organizationId);
    }
}

<?php

namespace App\Modules\Tenancy\Concerns;

use App\Modules\Tenancy\Exceptions\TenantMismatch;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\OrganizationScope;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For every tenant-owned model (a table with organization_id, DATA-007).
 *
 * - queries only see rows of the current tenant (OrganizationScope);
 * - new rows get the current tenant's organization_id;
 * - rows can never be written for another tenant, nor moved to one (SEC-004).
 *
 * @mixin Model
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            $current = app(TenantContext::class)->id();
            $given = $model->getAttribute('organization_id');

            if ($given === null) {
                if ($current === null) {
                    throw TenantMismatch::missingOrganization($model::class);
                }

                $model->setAttribute('organization_id', $current);

                return;
            }

            if ($current !== null && $given !== $current) {
                throw TenantMismatch::crossTenantWrite($model::class);
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('organization_id')) {
                throw TenantMismatch::organizationChanged($model::class);
            }
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}

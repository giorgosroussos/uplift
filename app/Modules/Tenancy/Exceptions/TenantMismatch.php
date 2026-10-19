<?php

namespace App\Modules\Tenancy\Exceptions;

use LogicException;

/**
 * Thrown when code tries to write a tenant-owned record for an organisation
 * other than the current tenant, or without any organisation (SEC-004).
 */
final class TenantMismatch extends LogicException
{
    public static function crossTenantWrite(string $model): self
    {
        return new self("Cannot write [{$model}] for an organization other than the current tenant.");
    }

    public static function organizationChanged(string $model): self
    {
        return new self("The organization of [{$model}] cannot be changed.");
    }

    public static function missingOrganization(string $model): self
    {
        return new self("Cannot create [{$model}] without an organization: no tenant is set and no organization_id was given.");
    }
}

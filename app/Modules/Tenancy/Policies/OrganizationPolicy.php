<?php

namespace App\Modules\Tenancy\Policies;

use App\Modules\Tenancy\Contracts\OrganizationMember;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Organisation actions (ARCH-008), authorised only through the
 * OrganizationMember contract: never Identity classes or role names.
 */
class OrganizationPolicy
{
    /**
     * Open and save the organisation settings (UC-033): only a member of that
     * organisation who administers it.
     */
    public function update(Authenticatable $user, Organization $organization): bool
    {
        return $user instanceof OrganizationMember
            && $user->organizationId() === (string) $organization->getKey()
            && $user->administersOrganization();
    }
}

<?php

namespace App\Modules\Tenancy\Contracts;

/**
 * A user as Tenancy sees it (ARCH-008): the organisation they belong to and
 * whether they administer it. Implemented by Identity's User, so Tenancy
 * authorises organisation actions without knowing Identity's classes or roles.
 */
interface OrganizationMember
{
    /**
     * Id of the one organisation the user belongs to (SEC-004).
     */
    public function organizationId(): string;

    /**
     * Whether the user administers their organisation.
     */
    public function administersOrganization(): bool;
}

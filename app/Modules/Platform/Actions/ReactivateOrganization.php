<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Events\OrganizationReactivated;
use App\Modules\Platform\Models\PlatformAdmin;
use App\Modules\Platform\Support\OrganizationStatusChange;
use App\Modules\Tenancy\Enums\OrganizationStatus;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Validation\ValidationException;

/**
 * UC-036: a platform admin reactivates a suspended organisation; its users can
 * log in again. Dispatches OrganizationReactivated (audited, SEC-010).
 */
final class ReactivateOrganization
{
    /**
     * @throws ValidationException when the organisation is not suspended
     */
    public function handle(PlatformAdmin $admin, Organization $organization): void
    {
        OrganizationStatusChange::apply($organization, OrganizationStatus::Active);

        OrganizationReactivated::dispatch((string) $organization->getKey(), (string) $admin->getKey());
    }
}

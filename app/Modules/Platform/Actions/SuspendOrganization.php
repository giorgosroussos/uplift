<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Events\OrganizationSuspended;
use App\Modules\Platform\Models\PlatformAdmin;
use App\Modules\Platform\Support\OrganizationStatusChange;
use App\Modules\Tenancy\Enums\OrganizationStatus;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Validation\ValidationException;

/**
 * UC-036: a platform admin suspends an active organisation. Its users can no
 * longer log in and open sessions end on the next request (Identity).
 * Dispatches OrganizationSuspended (audited, SEC-010).
 */
final class SuspendOrganization
{
    /**
     * @throws ValidationException when the organisation is not active
     */
    public function handle(PlatformAdmin $admin, Organization $organization): void
    {
        OrganizationStatusChange::apply($organization, OrganizationStatus::Suspended);

        OrganizationSuspended::dispatch((string) $organization->getKey(), (string) $admin->getKey());
    }
}

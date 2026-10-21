<?php

namespace App\Modules\Platform\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A platform admin reactivated a suspended organisation (UC-036); a platform
 * admin action, audited by P8-T3 (SEC-010). Dispatched after the change has
 * committed; carries ids only, never personal data.
 */
final class OrganizationReactivated
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $platformAdminId,
    ) {}
}

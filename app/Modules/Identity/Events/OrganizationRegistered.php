<?php

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An organisation and its first org admin were registered (UC-001).
 * Dispatched after the registration transaction has committed; carries ids
 * only, never personal data.
 */
final class OrganizationRegistered
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $adminUserId,
    ) {}
}

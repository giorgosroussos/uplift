<?php

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An org admin changed a user's role (UC-032); audited (SEC-010).
 * Dispatched after the change has committed; carries ids and role values
 * only, never personal data.
 */
final class UserRoleChanged
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $userId,
        public readonly string $actorId,
        public readonly string $fromRole,
        public readonly string $toRole,
    ) {}
}

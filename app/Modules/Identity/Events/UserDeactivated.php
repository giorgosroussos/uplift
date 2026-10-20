<?php

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An org admin deactivated a user (UC-032); audited (SEC-010).
 * Dispatched after the change has committed; carries ids only, never
 * personal data.
 */
final class UserDeactivated
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $userId,
        public readonly string $actorId,
    ) {}
}

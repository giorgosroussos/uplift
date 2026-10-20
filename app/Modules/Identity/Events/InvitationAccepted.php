<?php

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An invitation was accepted and its user account activated (UC-005).
 * Dispatched after the change has committed; carries ids only, never
 * personal data.
 */
final class InvitationAccepted
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $invitationId,
        public readonly string $userId,
    ) {}
}

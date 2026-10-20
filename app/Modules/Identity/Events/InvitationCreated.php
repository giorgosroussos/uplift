<?php

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An org admin invited an email address to the organisation (UC-032).
 * Dispatched after the change has committed; carries ids only, never
 * personal data.
 */
final class InvitationCreated
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $invitationId,
        public readonly string $invitedBy,
    ) {}
}

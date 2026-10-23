<?php

namespace App\Modules\Courses\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A member invited a student to a group (UC-019, SCOPE-025), for the
 * Notifications module to notify the invitee (INT-001 GroupInvitation).
 * Dispatched after the invitation has committed; carries ids only.
 */
final class GroupInvitationCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $groupMembershipId,
    ) {}
}

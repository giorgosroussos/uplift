<?php

namespace App\Modules\Courses\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A group membership changed status (UC-019, SCOPE-025): accepted,
 * declined, withdrawn or left, also through unenrolment and archiving.
 * fromStatus and toStatus are ENT-GroupMembership status values.
 * Dispatched after the change has committed; carries ids only.
 */
final class GroupMembershipChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $groupMembershipId,
        public readonly string $fromStatus,
        public readonly string $toStatus,
    ) {}
}

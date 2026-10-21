<?php

namespace App\Modules\Activities\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A teacher published an activity (UC-022), e.g. for the Notifications
 * module (INT-001 ActivityPublished). Dispatched after the status change
 * has committed; carries ids only.
 */
final class ActivityPublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $courseSpaceId,
        public readonly string $activityId,
        public readonly string $actorId,
    ) {}
}

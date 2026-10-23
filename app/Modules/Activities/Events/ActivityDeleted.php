<?php

namespace App\Modules\Activities\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The owning teacher deleted an activity (UC-040, SCOPE-029), e.g. for the
 * Audit module, which records activity.deleted. Dispatched after the soft
 * delete has committed; carries ids and the activity's type only. Nobody is
 * notified of a deletion.
 */
final class ActivityDeleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $activityId,
        public readonly string $actorId,
        public readonly string $type,
    ) {}
}

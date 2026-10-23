<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Activities\Events\ActivityDeleted;
use App\Modules\Audit\Actions\AuditRecord;
use App\Modules\Audit\Enums\ActorType;

/**
 * activity.deleted: the owning teacher deleted an activity (UC-040,
 * SCOPE-029). One entry; changes hold the activity's type.
 */
final class AuditActivityDeleted extends AuditListener
{
    protected function toRecord(object $event): AuditRecord
    {
        assert($event instanceof ActivityDeleted);

        return new AuditRecord(
            action: 'activity.deleted',
            actorType: ActorType::User,
            actorId: $event->actorId,
            subjectType: 'activity',
            subjectId: $event->activityId,
            organizationId: $event->organizationId,
            changes: ['type' => $event->type],
        );
    }
}

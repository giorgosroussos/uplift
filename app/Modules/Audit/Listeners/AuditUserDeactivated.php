<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Actions\AuditRecord;
use App\Modules\Audit\Enums\ActorType;
use App\Modules\Identity\Events\UserDeactivated;

/**
 * user.deactivated: an org admin deactivated a user (UC-032, SEC-010).
 * One entry without changes.
 */
final class AuditUserDeactivated extends AuditListener
{
    protected function toRecord(object $event): AuditRecord
    {
        assert($event instanceof UserDeactivated);

        return new AuditRecord(
            action: 'user.deactivated',
            actorType: ActorType::User,
            actorId: $event->actorId,
            subjectType: 'user',
            subjectId: $event->userId,
            organizationId: $event->organizationId,
        );
    }
}

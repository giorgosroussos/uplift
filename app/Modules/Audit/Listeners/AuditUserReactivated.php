<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Actions\AuditRecord;
use App\Modules\Audit\Enums\ActorType;
use App\Modules\Identity\Events\UserReactivated;

/**
 * user.reactivated: an org admin reactivated a deactivated user (UC-032, SEC-010).
 * One entry without changes.
 */
final class AuditUserReactivated extends AuditListener
{
    protected function toRecord(object $event): AuditRecord
    {
        assert($event instanceof UserReactivated);

        return new AuditRecord(
            action: 'user.reactivated',
            actorType: ActorType::User,
            actorId: $event->actorId,
            subjectType: 'user',
            subjectId: $event->userId,
            organizationId: $event->organizationId,
        );
    }
}

<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Actions\AuditRecord;
use App\Modules\Audit\Enums\ActorType;
use App\Modules\Identity\Events\UserRoleChanged;

/**
 * user.role_changed: an org admin changed a user's role (UC-032, SEC-010).
 * One entry; changes hold {role: {from, to}}.
 */
final class AuditUserRoleChanged extends AuditListener
{
    protected function toRecord(object $event): AuditRecord
    {
        assert($event instanceof UserRoleChanged);

        return new AuditRecord(
            action: 'user.role_changed',
            actorType: ActorType::User,
            actorId: $event->actorId,
            subjectType: 'user',
            subjectId: $event->userId,
            organizationId: $event->organizationId,
            changes: ['role' => ['from' => $event->fromRole, 'to' => $event->toRole]],
        );
    }
}

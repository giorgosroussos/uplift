<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Actions\AuditRecord;
use App\Modules\Audit\Enums\ActorType;
use App\Modules\Platform\Events\OrganizationReactivated;
use App\Modules\Tenancy\Enums\OrganizationStatus;

/**
 * organization.reactivated: a platform admin reactivated a suspended
 * organisation (UC-036, SEC-010). One entry on the organisation; changes hold
 * {status: {from: suspended, to: active}}.
 */
final class AuditOrganizationReactivated extends AuditListener
{
    protected function toRecord(object $event): AuditRecord
    {
        assert($event instanceof OrganizationReactivated);

        return new AuditRecord(
            action: 'organization.reactivated',
            actorType: ActorType::PlatformAdmin,
            actorId: $event->platformAdminId,
            subjectType: 'organization',
            subjectId: $event->organizationId,
            organizationId: $event->organizationId,
            changes: ['status' => [
                'from' => OrganizationStatus::Suspended->value,
                'to' => OrganizationStatus::Active->value,
            ]],
        );
    }
}

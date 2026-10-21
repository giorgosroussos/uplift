<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Actions\AuditRecord;
use App\Modules\Audit\Enums\ActorType;
use App\Modules\Platform\Events\OrganizationSuspended;
use App\Modules\Tenancy\Enums\OrganizationStatus;

/**
 * organization.suspended: a platform admin suspended an active organisation
 * (UC-036, SEC-010). One entry on the organisation; changes hold
 * {status: {from: active, to: suspended}}.
 */
final class AuditOrganizationSuspended extends AuditListener
{
    protected function toRecord(object $event): AuditRecord
    {
        assert($event instanceof OrganizationSuspended);

        return new AuditRecord(
            action: 'organization.suspended',
            actorType: ActorType::PlatformAdmin,
            actorId: $event->platformAdminId,
            subjectType: 'organization',
            subjectId: $event->organizationId,
            organizationId: $event->organizationId,
            changes: ['status' => [
                'from' => OrganizationStatus::Active->value,
                'to' => OrganizationStatus::Suspended->value,
            ]],
        );
    }
}

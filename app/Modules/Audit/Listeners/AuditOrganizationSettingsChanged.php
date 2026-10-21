<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Actions\AuditRecord;
use App\Modules\Audit\Enums\ActorType;
use App\Modules\Tenancy\Events\OrganizationSettingsChanged;

/**
 * organization.settings_changed: an org admin saved changed organisation
 * settings (UC-033, DATA-011, SEC-010). One entry per save; changes are the
 * dispatched {field: {from, to}} (scalar values, never file contents).
 */
final class AuditOrganizationSettingsChanged extends AuditListener
{
    protected function toRecord(object $event): AuditRecord
    {
        assert($event instanceof OrganizationSettingsChanged);

        return new AuditRecord(
            action: 'organization.settings_changed',
            actorType: ActorType::User,
            actorId: $event->actorId,
            subjectType: 'organization',
            subjectId: $event->organizationId,
            organizationId: $event->organizationId,
            changes: $event->changes,
        );
    }
}

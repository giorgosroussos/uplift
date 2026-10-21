<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Actions\AuditRecord;
use App\Modules\Audit\Enums\ActorType;
use App\Modules\Identity\Events\OrganizationRegistered;
use App\Modules\Tenancy\Models\Organization;

/**
 * organization.registered: the new org admin registered the organisation
 * (UC-001, SEC-010). One entry; changes hold the subdomain only.
 */
final class AuditOrganizationRegistered extends AuditListener
{
    protected function toRecord(object $event): AuditRecord
    {
        assert($event instanceof OrganizationRegistered);

        $subdomain = Organization::query()->whereKey($event->organizationId)->value('subdomain');

        return new AuditRecord(
            action: 'organization.registered',
            actorType: ActorType::User,
            actorId: $event->adminUserId,
            subjectType: 'organization',
            subjectId: $event->organizationId,
            organizationId: $event->organizationId,
            changes: ['subdomain' => $subdomain],
        );
    }
}

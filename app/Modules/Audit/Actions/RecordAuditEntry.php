<?php

namespace App\Modules\Audit\Actions;

use App\Modules\Audit\Models\AuditEntry;
use Carbon\CarbonImmutable;

/**
 * Appends one entry to the audit log (SEC-010). Timestamps are stored in UTC.
 */
final class RecordAuditEntry
{
    public function handle(AuditRecord $record): AuditEntry
    {
        $occurredAt = $record->occurredAt === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::instance($record->occurredAt)->utc();

        return AuditEntry::query()->create([
            'organization_id' => $record->organizationId,
            'actor_type' => $record->actorType,
            'actor_id' => $record->actorId,
            'action' => $record->action,
            'subject_type' => $record->subjectType,
            'subject_id' => $record->subjectId,
            'changes' => $record->changes,
            'occurred_at' => $occurredAt,
        ]);
    }
}

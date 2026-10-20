<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Actions\AuditRecord;
use App\Modules\Audit\Actions\RecordAuditEntry;

/**
 * Base of every audit listener: maps a domain event of another module to an
 * audit entry. Register concrete listeners in AuditServiceProvider::$listen.
 *
 * Listeners run synchronously so the entry is written in the same request
 * (and transaction) as the audited change.
 */
abstract class AuditListener
{
    public function __construct(private readonly RecordAuditEntry $recordAuditEntry) {}

    /**
     * The entry to write for this event, or null when it needs no entry.
     */
    abstract protected function toRecord(object $event): ?AuditRecord;

    public function handle(object $event): void
    {
        $record = $this->toRecord($event);

        if ($record !== null) {
            $this->recordAuditEntry->handle($record);
        }
    }
}

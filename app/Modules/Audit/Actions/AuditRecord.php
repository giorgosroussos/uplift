<?php

namespace App\Modules\Audit\Actions;

use App\Modules\Audit\Enums\ActorType;
use DateTimeInterface;

/**
 * What to write as one audit entry. Holds ids only, never personal data
 * (SEC-003): put values in $changes only when they are not personal.
 */
final readonly class AuditRecord
{
    /**
     * @param  array<string, mixed>|null  $changes
     */
    public function __construct(
        public string $action,
        public ActorType $actorType,
        public ?string $actorId,
        public string $subjectType,
        public string $subjectId,
        public ?string $organizationId = null,
        public ?array $changes = null,
        public ?DateTimeInterface $occurredAt = null,
    ) {}
}

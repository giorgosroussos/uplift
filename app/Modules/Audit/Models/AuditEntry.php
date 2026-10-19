<?php

namespace App\Modules\Audit\Models;

use App\Modules\Audit\Enums\ActorType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * ENT-AuditEntry: append-only record of security-relevant actions (SEC-010).
 *
 * Entries are never updated or deleted by application code; only the
 * retention prune (2 years) removes them, as a mass delete.
 *
 * @property string $id
 * @property string|null $organization_id
 * @property ActorType $actor_type
 * @property string|null $actor_id
 * @property string $action
 * @property string $subject_type
 * @property string $subject_id
 * @property array<string, mixed>|null $changes
 * @property CarbonImmutable $occurred_at
 */
class AuditEntry extends Model
{
    use HasUuids;
    use MassPrunable;

    /** Retention of ENT-AuditEntry. */
    public const RETENTION_YEARS = 2;

    public $timestamps = false;

    protected $table = 'audit_entries';

    protected $fillable = [
        'organization_id',
        'actor_type',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'changes',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw AppendOnlyViolation::for('update');
        });

        static::deleting(static function (): never {
            throw AppendOnlyViolation::for('delete');
        });
    }

    /**
     * Entries past their retention period.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('occurred_at', '<', now()->subYears(self::RETENTION_YEARS));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'changes' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}

<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Database\Factories\NotificationFactory;
use App\Modules\Notifications\Enums\NotificationKind;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use App\Modules\Tenancy\Support\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ENT-Notification: an in-app notification of one user (UC-007, DEC-002).
 * Tenant-owned; only its user reads and changes it (Policies/NotificationPolicy).
 * data carries ids and title snapshots, never personal data or bodies.
 * Deleted 180 days after creation (retention prune).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property NotificationKind $kind
 * @property array<string, mixed> $data
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[UseFactory(NotificationFactory::class)]
class Notification extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    use HasUuids;
    use MassPrunable;

    /** Retention of ENT-Notification. */
    public const int RETENTION_DAYS = 180;

    protected $table = 'notifications';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'kind',
        'data',
    ];

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Notifications past their retention period, of every organisation.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => NotificationKind::class,
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }
}

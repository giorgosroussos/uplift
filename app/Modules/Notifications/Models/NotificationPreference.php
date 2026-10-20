<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ENT-NotificationPreference: a user's choice to receive DEC-001
 * notification emails (DEC-002), one per user. Without a row the user
 * receives them. Account emails are never affected.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property bool $email_enabled
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
class NotificationPreference extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'email_enabled',
    ];

    /**
     * Whether the user receives DEC-001 notification emails.
     */
    public static function emailEnabledFor(User $user): bool
    {
        $enabled = static::query()->where('user_id', $user->getKey())->value('email_enabled');

        return $enabled === null || (bool) $enabled;
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
            'email_enabled' => 'boolean',
        ];
    }
}

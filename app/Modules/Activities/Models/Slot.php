<?php

namespace App\Modules\Activities\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * ENT-Slot: a bookable time slot with the number of bookings it takes
 * (SCOPE-011, SCOPE-028). Times are UTC.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $slot_activity_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $location
 * @property int $capacity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SlotActivity $slotActivity
 */
class Slot extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'starts_at',
        'ends_at',
        'location',
        'capacity',
    ];

    /**
     * Whether the slot has started, by the server's clock: it can no longer
     * be booked, and a booking in it can no longer be changed or cancelled
     * (SCOPE-028).
     */
    public function hasStarted(?CarbonImmutable $now = null): bool
    {
        return ! ($now ?? CarbonImmutable::now())->lessThan($this->starts_at);
    }

    /**
     * @return BelongsTo<SlotActivity, $this>
     */
    public function slotActivity(): BelongsTo
    {
        return $this->belongsTo(SlotActivity::class, 'slot_activity_id');
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'slot_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
        ];
    }
}

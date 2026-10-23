<?php

namespace App\Modules\Activities\Models;

use App\Modules\Activities\Database\Factories\SlotActivityFactory;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * ENT-SlotActivity: the subtype of a slot booking activity (DATA-001),
 * keyed by the activity id. It offers time slots with a capacity
 * (SCOPE-028); a student, or in a group activity a group, holds at most
 * one booking in it. slot_duration (seconds) only pre-fills the end of new
 * slots in the wizard.
 *
 * Bookings can be made, changed and cancelled while the activity is
 * published and its deadline (if any) has not passed, by the server's
 * clock (DATA-004); a slot that has started is out of reach (Slot::hasStarted).
 *
 * @property string $activity_id
 * @property string $organization_id
 * @property int|null $slot_duration
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Activity $activity
 * @property-read Collection<int, Slot> $slots
 */
#[UseFactory(SlotActivityFactory::class)]
class SlotActivity extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<SlotActivityFactory> */
    use HasFactory;

    protected $primaryKey = 'activity_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public static function forActivity(Activity $activity): ?self
    {
        return self::query()->find($activity->id);
    }

    public function isOpen(?CarbonImmutable $now = null): bool
    {
        $deadline = $this->activity->deadline_at;

        return $this->activity->isPublished()
            && ($deadline === null || ($now ?? CarbonImmutable::now())->lessThan($deadline));
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    /**
     * The slots in chronological order.
     *
     * @return HasMany<Slot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class, 'slot_activity_id')->orderBy('starts_at')->orderBy('ends_at')->orderBy('id');
    }

    /**
     * Every current booking of the activity.
     *
     * @return Builder<Booking>
     */
    public function bookings(): Builder
    {
        return Booking::query()->whereIn(
            'slot_id',
            Slot::query()->select('id')->where('slot_activity_id', $this->activity_id),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot_duration' => 'integer',
        ];
    }
}

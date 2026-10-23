<?php

namespace App\Modules\Activities\Database\Factories;

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Slot;
use App\Modules\Activities\Models\SlotActivity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Slot activities (the subtype row) of a new draft slot booking unless
 * ->for($activity, 'activity') is given; ->withSlots() adds slots.
 *
 * @extends Factory<SlotActivity>
 */
class SlotActivityFactory extends Factory
{
    protected $model = SlotActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_id' => fn (): string => (string) Activity::factory()->ofKind(ActivityKind::SlotBooking)->create()->getKey(),
            'organization_id' => fn (array $attributes): string => Activity::query()
                ->withoutGlobalScopes()
                ->whereKey($attributes['activity_id'])
                ->value('organization_id'),
            'slot_duration' => null,
        ];
    }

    /**
     * Slots of 30 minutes; each key is the start (anything Carbon parses,
     * e.g. "+1 day 10:00"), each value its capacity, optionally with a
     * location as [capacity, location].
     *
     * @param  array<string, int|array{0: int, 1: string|null}>  $slots
     */
    public function withSlots(array $slots = ['+1 day 10:00' => 1, '+1 day 10:30' => 2]): static
    {
        return $this->afterCreating(function (SlotActivity $slotActivity) use ($slots): void {
            foreach ($slots as $start => $spec) {
                [$capacity, $location] = is_array($spec) ? $spec : [$spec, null];
                $startsAt = Carbon::parse((string) $start)->utc();
                $slot = new Slot([
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->copy()->addMinutes(30),
                    'location' => $location,
                    'capacity' => $capacity,
                ]);
                $slot->organization_id = $slotActivity->organization_id;
                $slot->slot_activity_id = $slotActivity->activity_id;
                $slot->save();
            }
        });
    }
}

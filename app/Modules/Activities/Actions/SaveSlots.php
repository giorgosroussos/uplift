<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Slot;
use App\Modules\Activities\Models\SlotActivity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * UC-022 step 3 for a slot booking (wizard step "content"): the optional
 * slot duration and the slots with their capacity (SCOPE-028). The client
 * identifies every slot by a UUID, which it generates for new ones, so
 * autosaving the same list again changes nothing; an id of a slot outside
 * this activity is refused. Slots left out are deleted. Times arrive as
 * ISO 8601 and are stored in UTC.
 *
 * slot_duration only pre-fills the end of new slots in the wizard; the
 * slots' own times are never checked against it. Once slots are booked, a
 * booked slot cannot be removed and its capacity cannot drop below its
 * bookings. Called by the type inside the wizard's transaction, which
 * locks the activity row like BookSlot does.
 */
class SaveSlots
{
    public const int MIN_SLOTS = 1;

    public const int MAX_SLOTS = 100;

    public const int MAX_CAPACITY = 1000;

    /** slot_duration in seconds: 5 minutes to 24 hours. */
    public const int MIN_DURATION = 300;

    public const int MAX_DURATION = 86400;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Activity $activity, array $input): void
    {
        $slotActivity = SlotActivity::forActivity($activity);
        [$duration, $slots] = $this->validate($slotActivity, $input);

        if ($slotActivity === null) {
            $slotActivity = new SlotActivity;
            $slotActivity->activity_id = $activity->id;
            $slotActivity->organization_id = $activity->organization_id;
        }

        $slotActivity->slot_duration = $duration;
        $slotActivity->save();

        /** @var array<string, Slot> $existing */
        $existing = $slotActivity->slots()->get()->keyBy('id')->all();

        foreach ($slots as $item) {
            $slot = $existing[$item['id']] ?? null;

            if ($slot === null) {
                $slot = new Slot;
                $slot->id = $item['id'];
                $slot->organization_id = $slotActivity->organization_id;
                $slot->slot_activity_id = $slotActivity->activity_id;
            }

            $slot->fill([
                'starts_at' => $item['starts_at'],
                'ends_at' => $item['ends_at'],
                'location' => $item['location'],
                'capacity' => $item['capacity'],
            ])->save();
        }

        $removed = array_values(array_diff(array_keys($existing), array_column($slots, 'id')));

        if ($removed !== []) {
            Slot::query()->where('slot_activity_id', $slotActivity->activity_id)->whereKey($removed)->delete();
        }
    }

    /**
     * The saved step in the shape of the wizard step, or null when the
     * activity has no slots yet.
     *
     * @return array{slot_duration: int|null, slots: list<array{id: string, starts_at: string, ends_at: string, location: string|null, capacity: int}>}|null
     */
    public static function data(?SlotActivity $slotActivity): ?array
    {
        if ($slotActivity === null) {
            return null;
        }

        $slots = $slotActivity->slots()->get()
            ->map(fn (Slot $slot): array => [
                'id' => $slot->id,
                'starts_at' => $slot->starts_at->utc()->toIso8601String(),
                'ends_at' => $slot->ends_at->utc()->toIso8601String(),
                'location' => $slot->location,
                'capacity' => $slot->capacity,
            ])
            ->values()
            ->all();

        return $slots === [] ? null : ['slot_duration' => $slotActivity->slot_duration, 'slots' => $slots];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: int|null, 1: list<array{id: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, location: string|null, capacity: int}>}
     *
     * @throws ValidationException
     */
    private function validate(?SlotActivity $slotActivity, array $input): array
    {
        $validator = Validator::make($input, [
            'slot_duration' => ['nullable', 'integer', 'min:'.self::MIN_DURATION, 'max:'.self::MAX_DURATION],
            'slots' => ['present', 'array', 'list', 'min:'.self::MIN_SLOTS, 'max:'.self::MAX_SLOTS],
            'slots.*' => ['array'],
            'slots.*.id' => ['required', 'uuid', 'distinct'],
            'slots.*.starts_at' => ['required', 'date'],
            'slots.*.ends_at' => ['required', 'date'],
            'slots.*.location' => ['nullable', 'string', 'max:255'],
            'slots.*.capacity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_CAPACITY],
        ], [
            'slot_duration.min' => (string) __('activities.slots.errors.duration_range'),
            'slot_duration.max' => (string) __('activities.slots.errors.duration_range'),
            'slots.min' => (string) __('activities.slots.errors.min_slots', ['min' => self::MIN_SLOTS]),
            'slots.max' => (string) __('activities.slots.errors.max_slots', ['max' => self::MAX_SLOTS]),
        ], [
            'slot_duration' => (string) __('activities.slots.attributes.slot_duration'),
            'slots' => (string) __('activities.slots.attributes.slots'),
            'slots.*.id' => (string) __('activities.slots.attributes.slot'),
            'slots.*.starts_at' => (string) __('activities.slots.attributes.starts_at'),
            'slots.*.ends_at' => (string) __('activities.slots.attributes.ends_at'),
            'slots.*.location' => (string) __('activities.slots.attributes.location'),
            'slots.*.capacity' => (string) __('activities.slots.attributes.capacity'),
        ]);

        /** @var array{slot_duration?: int|string|null, slots: list<array{id: string, starts_at: string, ends_at: string, location?: string|null, capacity: int|string}>} $data */
        $data = $validator->validate();
        $errors = [];
        $slots = [];

        foreach ($data['slots'] as $index => $slot) {
            $startsAt = self::parse((string) $slot['starts_at']);
            $endsAt = self::parse((string) $slot['ends_at']);

            if ($startsAt === null) {
                $errors["slots.{$index}.starts_at"] = [(string) __('validation.date', ['attribute' => __('activities.slots.attributes.starts_at')])];
            }

            if ($endsAt === null) {
                $errors["slots.{$index}.ends_at"] = [(string) __('validation.date', ['attribute' => __('activities.slots.attributes.ends_at')])];
            } elseif ($startsAt !== null && ! $endsAt->greaterThan($startsAt)) {
                $errors["slots.{$index}.ends_at"] = [(string) __('activities.slots.errors.ends_before_start')];
            }

            $location = isset($slot['location']) ? trim((string) $slot['location']) : '';
            $slots[] = [
                'id' => (string) $slot['id'],
                'starts_at' => $startsAt ?? CarbonImmutable::now(),
                'ends_at' => $endsAt ?? CarbonImmutable::now(),
                'location' => $location === '' ? null : $location,
                'capacity' => (int) $slot['capacity'],
            ];
        }

        /** @var array<string, int> $booked slot id => number of bookings */
        $booked = [];
        $ownIds = [];

        if ($slotActivity !== null) {
            foreach ($slotActivity->slots()->withCount('bookings')->get() as $slot) {
                $ownIds[] = $slot->id;
                $booked[$slot->id] = (int) $slot->getAttribute('bookings_count');
            }
        }

        $newIds = array_values(array_diff(array_column($slots, 'id'), $ownIds));
        $taken = $newIds === [] ? [] : Slot::query()->withoutGlobalScopes()->whereKey($newIds)
            ->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();

        foreach ($slots as $index => $slot) {
            // A primary key is global: only the activity's own slots may be addressed.
            if (in_array($slot['id'], $taken, true)) {
                $errors["slots.{$index}.id"] = [(string) __('activities.slots.errors.foreign_id')];
            }

            // SCOPE-028: never fewer places than bookings.
            $held = $booked[$slot['id']] ?? 0;

            if ($slot['capacity'] < $held) {
                $errors["slots.{$index}.capacity"] = [(string) __('activities.slots.errors.capacity_below_bookings', ['count' => $held])];
            }
        }

        $kept = array_column($slots, 'id');

        foreach ($booked as $id => $held) {
            if ($held > 0 && ! in_array($id, $kept, true)) {
                $errors['slots'] = [(string) __('activities.slots.errors.remove_booked')];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $duration = $data['slot_duration'] ?? null;

        return [$duration === null ? null : (int) $duration, $slots];
    }

    private static function parse(string $value): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}

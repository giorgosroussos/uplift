<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Models\Booking;
use App\Modules\Activities\Models\Slot;
use App\Modules\Activities\Models\SlotActivity;
use App\Modules\Activities\Support\ActingGroups;
use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * UC-016 step 1: a slot booking as its viewer may see it. Everybody who
 * opens it sees each slot with its times, location and remaining places.
 * A student sees only their own booking, or in a group activity the
 * bookings of the groups they are a current member of, never who holds
 * other bookings (OWN-010, SCOPE-028); the owning teacher also sees who
 * holds each slot.
 */
class ShowSlotBooking
{
    public function __construct(private readonly ActingGroups $groups) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, SlotActivity $slotActivity): array
    {
        $activity = $slotActivity->activity;
        $gate = Gate::forUser($user);
        $canBook = $gate->allows('book', $slotActivity);
        $showHolders = $gate->allows('viewBookings', $slotActivity);

        /** @var array<string, list<Booking>> $bookingsBySlot */
        $bookingsBySlot = [];

        foreach ($slotActivity->bookings()->with(['student', 'group'])->get() as $booking) {
            $bookingsBySlot[$booking->slot_id][] = $booking;
        }

        $slots = $slotActivity->slots()->get()->map(function (Slot $slot) use ($bookingsBySlot, $showHolders): array {
            $bookings = $bookingsBySlot[$slot->id] ?? [];
            $row = [
                'id' => $slot->id,
                'starts_at' => $slot->starts_at->utc()->toIso8601String(),
                'ends_at' => $slot->ends_at->utc()->toIso8601String(),
                'location' => $slot->location,
                'capacity' => $slot->capacity,
                'remaining' => max(0, $slot->capacity - count($bookings)),
                'started' => $slot->hasStarted(),
            ];

            if ($showHolders) {
                $row['holders'] = array_map(static fn (Booking $booking): string => self::holderName($booking), $bookings);
            }

            return $row;
        })->values()->all();

        return [
            'slotBooking' => [
                'activity_id' => $activity->id,
                'title' => $activity->title,
                'description' => $activity->description,
                'status' => $activity->status->value,
                'course_space_title' => $activity->courseSpace->title,
                'deadline_at' => $activity->deadline_at?->toIso8601String(),
                'is_group' => $activity->is_group,
                'group_size' => $activity->group_size,
                'is_open' => $slotActivity->isOpen(),
                'slots' => $slots,
            ],
            'booking' => $canBook && ! $activity->is_group ? $this->ownBooking($user, $slotActivity) : null,
            'groups' => $canBook && $activity->is_group ? $this->groupBookings($user, $slotActivity) : [],
            'can' => [
                'book' => $canBook && $slotActivity->isOpen(),
                'viewHolders' => $showHolders,
                'update' => $gate->allows('update', $activity),
            ],
        ];
    }

    /**
     * @return array{slot_id: string}|null
     */
    private function ownBooking(User $student, SlotActivity $slotActivity): ?array
    {
        $booking = BookSlot::currentBooking($slotActivity, ['student_id' => (string) $student->getKey(), 'group_id' => null]);

        return $booking === null ? null : ['slot_id' => $booking->slot_id];
    }

    /**
     * The student's current groups in the space with their booking and
     * whether they may act in this activity (SCOPE-025).
     *
     * @return list<array{id: string, name: string, members: int, slot_id: string|null, too_large: bool, in_other_group: bool}>
     */
    private function groupBookings(User $student, SlotActivity $slotActivity): array
    {
        $activity = $slotActivity->activity;
        $participating = $slotActivity->bookings()->whereNotNull('group_id')->pluck('slot_id', 'group_id')
            ->mapWithKeys(fn (mixed $slotId, mixed $groupId): array => [(string) $groupId => (string) $slotId])
            ->all();

        return $this->groups->of($student, $activity)
            ->map(fn (Group $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'members' => count($this->groups->memberIds($group)),
                'slot_id' => $participating[$group->id] ?? null,
                'too_large' => $this->groups->tooLarge($group, $activity),
                'in_other_group' => $this->groups->overlaps($group, array_map('strval', array_keys($participating))),
            ])
            ->values()
            ->all();
    }

    private static function holderName(Booking $booking): string
    {
        if ($booking->group_id !== null) {
            return (string) $booking->group?->name;
        }

        return (string) $booking->student?->name;
    }
}

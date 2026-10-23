<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Booking;
use App\Modules\Activities\Models\Slot;
use App\Modules\Activities\Models\SlotActivity;
use App\Modules\Activities\Support\ActingGroups;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * UC-016 steps 2-3: a student books a slot with free capacity that has not
 * started, or moves their booking to another one, until the deadline
 * (SCOPE-028). A booking in a slot that has started can no longer be
 * moved. In a group activity the student chooses which of their groups
 * acts and the booking belongs to that group (SCOPE-012, SCOPE-025); the
 * group may not have more current members than the activity's
 * group_size, nor share a current member with another group that already
 * holds a booking in the activity.
 *
 * The activity row is locked for the whole decision, so concurrent
 * bookings in the same activity are serialised: a slot never gets more
 * bookings than its capacity, and a holder never holds two bookings. A
 * change moves the existing row to the new slot.
 */
class BookSlot
{
    public function __construct(private readonly ActingGroups $groups) {}

    /**
     * @param  array<string, mixed>  $input  {slot_id: uuid, group_id?: uuid}
     *
     * @throws ValidationException (field "slot" or "group_id") when the booking is refused
     */
    public function handle(User $student, SlotActivity $slotActivity, array $input): Booking
    {
        /** @var array{slot_id: string, group_id?: string|null} $data */
        $data = Validator::make($input, [
            'slot_id' => ['required', 'uuid'],
            'group_id' => ['nullable', 'uuid'],
        ], [
            'slot_id.required' => (string) __('activities.slots.book.errors.choose'),
        ], [
            'slot_id' => (string) __('activities.slots.attributes.slot'),
            'group_id' => (string) __('activities.slots.attributes.group'),
        ])->validate();

        return DB::transaction(function () use ($student, $slotActivity, $data): Booking {
            // Serialises every booking of the activity, and against closing and editing it.
            $activity = Activity::query()->lockForUpdate()->findOrFail($slotActivity->activity_id);
            $locked = SlotActivity::query()->findOrFail($slotActivity->activity_id);
            $locked->setRelation('activity', $activity);

            self::ensureOpen($locked);

            $slot = Slot::query()
                ->where('slot_activity_id', $locked->activity_id)
                ->find($data['slot_id']);

            if ($slot === null) {
                self::refuse('unknown_slot');
            }

            $holder = $this->holder($student, $locked, $data['group_id'] ?? null);
            $current = self::currentBooking($locked, $holder);

            // OWN-010: only the student's own or their group's booking changes.
            if ($current !== null && Gate::forUser($student)->denies('update', $current)) {
                self::refuse('not_yours');
            }

            if ($current !== null && $current->slot_id === $slot->id) {
                return $current;
            }

            if ($slot->hasStarted()) {
                self::refuse('started');
            }

            if ($current !== null && $current->slot->hasStarted()) {
                self::refuse('current_started');
            }

            if ($slot->bookings()->count() >= $slot->capacity) {
                self::refuse('full');
            }

            $booking = $current ?? new Booking;
            $booking->organization_id = $locked->organization_id;
            $booking->student_id = $holder['student_id'];
            $booking->group_id = $holder['group_id'];
            $booking->slot_id = $slot->id;
            $booking->save();

            return $booking;
        });
    }

    /**
     * Who holds the booking: the student in an individual activity, else
     * the group the student acts for.
     *
     * @return array{student_id: string|null, group_id: string|null}
     *
     * @throws ValidationException
     */
    private function holder(User $student, SlotActivity $slotActivity, ?string $groupId): array
    {
        $activity = $slotActivity->activity;

        if (! $activity->is_group) {
            return ['student_id' => (string) $student->getKey(), 'group_id' => null];
        }

        if ($groupId === null) {
            self::refuse('choose_group', 'group_id');
        }

        $group = $this->groups->find($student, $activity, $groupId);

        if ($group === null) {
            self::refuse('not_your_group', 'group_id');
        }

        if ($this->groups->tooLarge($group, $activity)) {
            self::refuse('group_too_large', 'group_id', ['size' => (int) $activity->group_size]);
        }

        $participating = $slotActivity->bookings()->whereNotNull('group_id')->pluck('group_id')
            ->map(fn (mixed $id): string => (string) $id)->values()->all();

        if ($this->groups->overlaps($group, $participating)) {
            self::refuse('member_in_other_group', 'group_id');
        }

        return ['student_id' => null, 'group_id' => $group->id];
    }

    /**
     * @param  array{student_id: string|null, group_id: string|null}  $holder
     */
    public static function currentBooking(SlotActivity $slotActivity, array $holder): ?Booking
    {
        $query = $slotActivity->bookings();

        return $holder['group_id'] !== null
            ? $query->where('group_id', $holder['group_id'])->first()
            : $query->where('student_id', $holder['student_id'])->first();
    }

    /**
     * Bookings change only while the activity is published and before its
     * deadline (SCOPE-028, server time).
     *
     * @throws ValidationException
     */
    public static function ensureOpen(SlotActivity $slotActivity): void
    {
        if ($slotActivity->isOpen()) {
            return;
        }

        self::refuse($slotActivity->activity->isPublished() ? 'deadline_passed' : 'closed');
    }

    /**
     * @param  array<string, int|string>  $replace
     *
     * @throws ValidationException
     */
    public static function refuse(string $reason, string $field = 'slot', array $replace = []): never
    {
        throw ValidationException::withMessages([
            $field => (string) __("activities.slots.book.refused.{$reason}", $replace),
        ]);
    }
}

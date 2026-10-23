<?php

namespace App\Modules\Activities\Policies;

use App\Modules\Activities\Models\Booking;
use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * OWN-010 on ENT-Booking: a student sees and changes only their own
 * booking or the booking of a group they are a current member of; a
 * teacher sees the bookings of their own activities only and changes
 * none. Nobody lists bookings across activities. Changes go through
 * BookSlot and CancelBooking, which only ever touch the acting student's
 * own or group's booking.
 */
class BookingPolicy
{
    public function viewAny(User $actor): bool
    {
        return false;
    }

    public function view(User $actor, Booking $booking): bool
    {
        if (! $actor->canAct() || $actor->organization_id !== $booking->organization_id) {
            return false;
        }

        if ($actor->hasRole(Role::Student)) {
            return $this->holds($actor, $booking);
        }

        $space = $booking->slot->slotActivity->activity->courseSpace;

        return $actor->hasRole(Role::Teacher) && Gate::forUser($actor)->allows('manageActivities', $space);
    }

    public function update(User $actor, Booking $booking): bool
    {
        return $this->changes($actor, $booking);
    }

    public function delete(User $actor, Booking $booking): bool
    {
        return $this->changes($actor, $booking);
    }

    private function changes(User $actor, Booking $booking): bool
    {
        return $actor->canAct()
            && $actor->hasRole(Role::Student)
            && $actor->organization_id === $booking->organization_id
            && $this->holds($actor, $booking);
    }

    /**
     * The student's own booking, or one of a (not deleted) group they are
     * a current member of.
     */
    private function holds(User $student, Booking $booking): bool
    {
        if ($booking->student_id !== null) {
            return $booking->student_id === $student->getKey();
        }

        $group = $booking->group_id === null ? null : Group::query()->find($booking->group_id);

        return $group !== null && $group->hasMember($student);
    }
}

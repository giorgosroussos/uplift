<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\SlotActivity;
use App\Modules\Activities\Support\ActingGroups;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * UC-016 step 3: a student cancels their own booking, or the booking of a
 * group they are a current member of, until the deadline and while its
 * slot has not started (SCOPE-028); the row is deleted and the slot
 * regains the place. Otherwise the cancellation is refused and nothing
 * changes.
 */
class CancelBooking
{
    public function __construct(private readonly ActingGroups $groups) {}

    /**
     * @param  array<string, mixed>  $input  {group_id?: uuid}
     *
     * @throws ValidationException when the cancellation is refused
     */
    public function handle(User $student, SlotActivity $slotActivity, array $input): void
    {
        /** @var array{group_id?: string|null} $data */
        $data = Validator::make($input, [
            'group_id' => ['nullable', 'uuid'],
        ], [], [
            'group_id' => (string) __('activities.slots.attributes.group'),
        ])->validate();

        DB::transaction(function () use ($student, $slotActivity, $data): void {
            $activity = Activity::query()->lockForUpdate()->findOrFail($slotActivity->activity_id);
            $locked = SlotActivity::query()->findOrFail($slotActivity->activity_id);
            $locked->setRelation('activity', $activity);

            BookSlot::ensureOpen($locked);

            if ($activity->is_group) {
                $groupId = $data['group_id'] ?? null;
                $group = $groupId === null ? null : $this->groups->find($student, $activity, $groupId);

                if ($group === null) {
                    BookSlot::refuse('not_your_group', 'group_id');
                }

                $holder = ['student_id' => null, 'group_id' => $group->id];
            } else {
                $holder = ['student_id' => (string) $student->getKey(), 'group_id' => null];
            }

            $booking = BookSlot::currentBooking($locked, $holder);

            if ($booking === null) {
                BookSlot::refuse('no_booking');
            }

            // OWN-010: only the student's own or their group's booking is cancelled.
            if (Gate::forUser($student)->denies('delete', $booking)) {
                BookSlot::refuse('not_yours');
            }

            if ($booking->slot->hasStarted()) {
                BookSlot::refuse('current_started');
            }

            $booking->delete();
        });
    }
}

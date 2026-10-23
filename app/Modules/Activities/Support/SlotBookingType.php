<?php

namespace App\Modules\Activities\Support;

use App\Modules\Activities\Actions\SaveSlots;
use App\Modules\Activities\Contracts\ActivityType;
use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\SubmissionStatus;
use App\Modules\Activities\Enums\WizardStep;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Booking;
use App\Modules\Activities\Models\SlotActivity;

/**
 * The slot booking activity type (ARCH-004, UC-016), registered with the
 * registry by ActivitiesServiceProvider. Its subtype row is
 * ENT-SlotActivity; the wizard shows only its content step: the optional
 * slot duration, which pre-fills the end of new slots, and the slots with
 * their capacity (SCOPE-028). Whether students book alone or in groups,
 * and the group size, are basics of the activity.
 *
 * Bookings are not graded; SaveSlots keeps booked slots and enough places
 * for their bookings instead of a grading lock.
 */
final class SlotBookingType implements ActivityType
{
    public function __construct(private readonly SaveSlots $slots) {}

    public function kind(): ActivityKind
    {
        return ActivityKind::SlotBooking;
    }

    public function component(WizardStep $step): ?string
    {
        return $step === WizardStep::Content ? 'Activities/SlotBooking/Slots' : null;
    }

    public function stepData(Activity $activity, WizardStep $step): ?array
    {
        return $step === WizardStep::Content ? SaveSlots::data(SlotActivity::forActivity($activity)) : null;
    }

    public function rules(Activity $activity, WizardStep $step): array
    {
        return $step === WizardStep::Content
            ? ['slot_duration' => ['nullable'], 'slots' => ['present', 'array', 'list'], 'slots.*' => ['array']]
            : [];
    }

    public function save(Activity $activity, WizardStep $step, array $data): void
    {
        if ($step === WizardStep::Content) {
            $this->slots->handle($activity, $data);
        }
    }

    public function changesGrading(Activity $activity, WizardStep $step, array $data): bool
    {
        return false;
    }

    public function publishErrors(Activity $activity): array
    {
        $slotActivity = SlotActivity::forActivity($activity);

        if ($slotActivity === null || ! $slotActivity->slots()->exists()) {
            return ['slots' => (string) __('activities.slots.errors.min_slots', ['min' => SaveSlots::MIN_SLOTS])];
        }

        return [];
    }

    /**
     * Any current booking (SCOPE-029): a cancelled booking no longer exists.
     */
    public function hasSubmissions(Activity $activity): bool
    {
        $slotActivity = SlotActivity::forActivity($activity);

        return $slotActivity !== null && $slotActivity->bookings()->exists();
    }

    /**
     * Every current booking: the student or group that holds a slot, since
     * the last change of the booking. A booking is not graded.
     */
    public function submissions(Activity $activity, ?SubmissionStatus $status): array
    {
        $slotActivity = SlotActivity::forActivity($activity);

        if ($slotActivity === null || ($status !== null && $status !== SubmissionStatus::Submitted)) {
            return [];
        }

        $url = route('activities.slots.show', $activity);

        return $slotActivity->bookings()->with(['student', 'group'])->get()
            ->map(fn (Booking $booking): Submission => new Submission(
                id: $booking->id,
                participant: $booking->group_id !== null ? (string) $booking->group?->name : (string) $booking->student?->name,
                submittedAt: $booking->updated_at ?? $booking->created_at ?? now(),
                status: SubmissionStatus::Submitted,
                url: $url,
            ))
            ->values()
            ->all();
    }
}

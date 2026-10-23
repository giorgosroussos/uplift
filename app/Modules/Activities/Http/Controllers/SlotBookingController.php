<?php

namespace App\Modules\Activities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Activities\Actions\BookSlot;
use App\Modules\Activities\Actions\CancelBooking;
use App\Modules\Activities\Actions\ShowSlotBooking;
use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\SlotActivity;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-016: a slot booking's page, booking (or moving) a slot and cancelling
 * the booking. Route model binding only finds activities of the current
 * tenant; SlotActivityPolicy decides who opens the page and who books,
 * and the actions only touch the acting student's own or group's booking
 * (OWN-010).
 */
class SlotBookingController extends Controller
{
    public function show(Request $request, Activity $activity, ShowSlotBooking $show): Response
    {
        $slotActivity = $this->slotActivity($activity);
        Gate::authorize('view', $slotActivity);

        return Inertia::render('Activities/SlotBooking/Show', $show->handle($this->user($request), $slotActivity));
    }

    public function book(Request $request, Activity $activity, BookSlot $book): RedirectResponse
    {
        $slotActivity = $this->authorizeBooking($activity);

        $book->handle($this->user($request), $slotActivity, $request->only('slot_id', 'group_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('activities.slots.flash.booked')]);

        return to_route('activities.slots.show', $activity);
    }

    public function cancel(Request $request, Activity $activity, CancelBooking $cancel): RedirectResponse
    {
        $slotActivity = $this->authorizeBooking($activity);

        $cancel->handle($this->user($request), $slotActivity, $request->only('group_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('activities.slots.flash.cancelled')]);

        return to_route('activities.slots.show', $activity);
    }

    private function authorizeBooking(Activity $activity): SlotActivity
    {
        $slotActivity = $this->slotActivity($activity);
        // A draft does not exist for students (404), like the activity itself.
        Gate::authorize('view', $slotActivity);
        Gate::authorize('book', $slotActivity);

        return $slotActivity;
    }

    private function slotActivity(Activity $activity): SlotActivity
    {
        $slotActivity = $activity->type === ActivityKind::SlotBooking ? SlotActivity::forActivity($activity) : null;

        abort_if($slotActivity === null, 404);

        return $slotActivity;
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

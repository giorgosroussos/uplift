<?php

namespace App\Modules\Activities\Policies;

use App\Modules\Activities\Models\SlotActivity;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * UC-016: who opens a slot booking, books in it and sees who holds which
 * slot. Opening follows the activity (ActivityPolicy::view: the owning
 * teacher and the enrolled students of an active space; a draft exists
 * only for its owner). Only an enrolled student books; only the owning
 * teacher sees the holders (OWN-010).
 */
class SlotActivityPolicy
{
    public function view(User $actor, SlotActivity $slotActivity): Response
    {
        return Gate::forUser($actor)->inspect('view', $slotActivity->activity);
    }

    /**
     * Whether the actor is someone who books in the activity; whether it is
     * open and the slot has room is up to BookSlot.
     */
    public function book(User $actor, SlotActivity $slotActivity): bool
    {
        $activity = $slotActivity->activity;

        return $actor->canAct()
            && $actor->hasRole(Role::Student)
            && $actor->organization_id === $slotActivity->organization_id
            && ! $activity->isDraft()
            && Gate::forUser($actor)->allows('participate', $activity->courseSpace);
    }

    public function viewBookings(User $actor, SlotActivity $slotActivity): bool
    {
        return $actor->organization_id === $slotActivity->organization_id
            && Gate::forUser($actor)->allows('manageActivities', $slotActivity->activity->courseSpace);
    }
}

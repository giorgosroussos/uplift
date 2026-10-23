<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Courses\Support\GroupLifecycle;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-019: the invitee accepts (becomes a member, joined_at set) or declines a
 * pending invitation. Only a still pending invitation of a group that still
 * exists can be answered, and only a student still enrolled in the space
 * accepts; membership of other groups of the space does not matter
 * (SCOPE-025). Dispatches GroupMembershipChanged after commit.
 */
class RespondToGroupInvitation
{
    public function __construct(private readonly GroupLifecycle $lifecycle) {}

    public function accept(User $student, GroupMembership $invitation): GroupMembership
    {
        return $this->respond($student, $invitation, GroupMembershipStatus::Member);
    }

    public function decline(User $student, GroupMembership $invitation): GroupMembership
    {
        return $this->respond($student, $invitation, GroupMembershipStatus::Declined);
    }

    private function respond(User $student, GroupMembership $invitation, GroupMembershipStatus $to): GroupMembership
    {
        DB::transaction(function () use ($student, $invitation, $to): void {
            $space = CourseSpace::query()
                ->whereKey($invitation->group->course_space_id)
                ->sharedLock()
                ->firstOrFail();
            $group = $this->lifecycle->lock($invitation->group_id);
            $invitation->refresh();

            if ($group === null || ! $invitation->isInvited()) {
                throw ValidationException::withMessages(['invitation' => __('groups.errors.invitation_closed')]);
            }

            if ($to === GroupMembershipStatus::Member && (! $space->isActive() || ! $space->hasEnrolled($student))) {
                throw ValidationException::withMessages(['invitation' => __('groups.errors.not_enrolled')]);
            }

            $this->lifecycle->change($invitation, $to);
        });

        return $invitation;
    }
}

<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Courses\Support\GroupLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-019: any member withdraws a pending invitation; the invitee can no
 * longer accept it (SCOPE-025). Dispatches GroupMembershipChanged after
 * commit.
 */
class WithdrawGroupInvitation
{
    public function __construct(private readonly GroupLifecycle $lifecycle) {}

    public function handle(GroupMembership $invitation): GroupMembership
    {
        DB::transaction(function () use ($invitation): void {
            $group = $this->lifecycle->lock($invitation->group_id);
            $invitation->refresh();

            if ($group === null || ! $invitation->isInvited()) {
                throw ValidationException::withMessages(['invitation' => __('groups.errors.invitation_closed')]);
            }

            $this->lifecycle->change($invitation, GroupMembershipStatus::Withdrawn);
        });

        return $invitation;
    }
}

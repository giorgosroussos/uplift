<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Courses\Support\GroupLifecycle;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-019: a member leaves the group (left, left_at set). The owner leaves
 * only after transferring ownership while other members remain; when the
 * last member leaves, the group is soft-deleted and its pending invitations
 * withdrawn (SCOPE-025). Returns whether the group still exists.
 */
class LeaveGroup
{
    public function __construct(private readonly GroupLifecycle $lifecycle) {}

    public function handle(User $student, Group $group): bool
    {
        return DB::transaction(function () use ($student, $group): bool {
            $locked = $this->lifecycle->lock($group->id);

            /** @var GroupMembership|null $membership */
            $membership = $locked?->memberships()
                ->where('student_id', $student->getKey())
                ->where('status', GroupMembershipStatus::Member)
                ->first();

            if ($locked === null || $membership === null) {
                throw ValidationException::withMessages(['group' => __('groups.errors.not_a_member')]);
            }

            $others = $locked->memberships()
                ->where('status', GroupMembershipStatus::Member)
                ->whereKeyNot($membership->id)
                ->exists();

            if ($others && $locked->isOwnedBy($student)) {
                throw ValidationException::withMessages(['group' => __('groups.errors.owner_must_transfer')]);
            }

            $this->lifecycle->change($membership, GroupMembershipStatus::Left);
            $this->lifecycle->afterMemberLeft($locked);

            return ! $locked->trashed();
        });
    }
}

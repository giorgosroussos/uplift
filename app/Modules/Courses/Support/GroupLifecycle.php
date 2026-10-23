<?php

namespace App\Modules\Courses\Support;

use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Events\GroupMembershipChanged;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;

/**
 * The shared steps of the group rules (SCOPE-025). Every method runs inside
 * the caller's transaction, with the group row locked by the caller
 * (lock()); events are dispatched after commit.
 */
final class GroupLifecycle
{
    /**
     * Locks the group row until the transaction ends, so concurrent changes
     * of the same group run one after the other. Null when the group is gone
     * (deleted meanwhile).
     */
    public function lock(string $groupId): ?Group
    {
        return Group::query()->whereKey($groupId)->lockForUpdate()->first();
    }

    /**
     * Moves one row along the state model and dispatches
     * GroupMembershipChanged after commit.
     */
    public function change(GroupMembership $membership, GroupMembershipStatus $to): void
    {
        $from = $membership->status;
        $membership->transitionTo($to);

        GroupMembershipChanged::dispatch(
            $membership->organization_id,
            $membership->id,
            $from->value,
            $to->value,
        );
    }

    /**
     * After a member left: a group with no members left is deleted; an owner
     * who left hands ownership to the member who joined first.
     */
    public function afterMemberLeft(Group $group): void
    {
        /** @var GroupMembership|null $next */
        $next = $group->memberships()
            ->where('status', GroupMembershipStatus::Member)
            ->orderBy('joined_at')
            ->orderBy('created_at')
            ->first();

        if ($next === null) {
            $this->delete($group, releaseMembers: false);

            return;
        }

        $stillMember = $group->memberships()
            ->where('student_id', $group->owner_id)
            ->where('status', GroupMembershipStatus::Member)
            ->exists();

        if (! $stillMember) {
            $group->owner_id = $next->student_id;
            $group->save();
        }
    }

    /**
     * Soft-deletes the group and withdraws its pending invitations; with
     * releaseMembers its members also become left (archiving). Nothing that
     * refers to the group (attempts, topic choices, proposals, bookings) is
     * touched.
     */
    public function delete(Group $group, bool $releaseMembers): void
    {
        $statuses = $releaseMembers ? GroupMembershipStatus::open() : [GroupMembershipStatus::Invited];

        $group->memberships()
            ->whereIn('status', $statuses)
            ->orderBy('created_at')
            ->get()
            ->each(fn (GroupMembership $row) => $this->change(
                $row,
                $row->isMember() ? GroupMembershipStatus::Left : GroupMembershipStatus::Withdrawn,
            ));

        $group->delete();
    }
}

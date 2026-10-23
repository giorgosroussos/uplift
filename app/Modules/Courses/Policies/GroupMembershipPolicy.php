<?php

namespace App\Modules\Courses\Policies;

use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * OWN-005 on ENT-GroupMembership: a row is seen by whoever sees its group
 * (GroupPolicy::view); only the invitee answers their invitation, only a
 * member of the group withdraws it; teachers and org admins change nothing.
 * Rows of a deleted group belong to nobody.
 */
class GroupMembershipPolicy
{
    public function view(User $actor, GroupMembership $membership): bool
    {
        $group = $this->group($actor, $membership);

        return $group !== null && Gate::forUser($actor)->allows('view', $group);
    }

    /**
     * Accept or decline.
     */
    public function respond(User $actor, GroupMembership $membership): bool
    {
        $group = $this->group($actor, $membership);

        return $group !== null
            && $actor->hasRole(Role::Student)
            && $membership->student_id === $actor->getKey()
            && Gate::forUser($actor)->allows('participate', $group->courseSpace);
    }

    public function withdraw(User $actor, GroupMembership $membership): bool
    {
        $group = $this->group($actor, $membership);

        return $group !== null && Gate::forUser($actor)->allows('invite', $group);
    }

    public function update(User $actor, GroupMembership $membership): bool
    {
        return false;
    }

    public function delete(User $actor, GroupMembership $membership): bool
    {
        return false;
    }

    private function group(User $actor, GroupMembership $membership): ?Group
    {
        if (! $actor->canAct() || $actor->organization_id !== $membership->organization_id) {
            return null;
        }

        /** @var Group|null $group */
        $group = $membership->group;

        return $group === null || $group->trashed() ? null : $group;
    }
}

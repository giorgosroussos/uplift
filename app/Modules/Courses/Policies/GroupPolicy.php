<?php

namespace App\Modules\Courses\Policies;

use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * OWN-005 and SCOPE-025: a student sees only groups they belong to or are
 * invited to; only members invite and withdraw invitations, and only the
 * owner renames, transfers or dissolves the group. The teacher who owns the
 * course space, and org admins of its organisation, see its groups
 * read-only. Students act only in a space they participate in (enrolled,
 * active); an archived space is 404 for them (CourseSpacePolicy).
 *
 * Abilities given a course space: viewGroups, createGroup.
 */
class GroupPolicy
{
    /**
     * The groups page of a space.
     */
    public function viewGroups(User $actor, CourseSpace $space): Response
    {
        if (! $this->sameOrganization($actor, $space->organization_id)) {
            return Response::denyAsNotFound();
        }

        return match ($actor->role) {
            Role::Student => Gate::forUser($actor)->inspect('participate', $space),
            Role::Teacher => $space->isOwnedBy($actor) ? Response::allow() : Response::deny(),
            Role::OrgAdmin => Response::allow(),
        };
    }

    public function createGroup(User $actor, CourseSpace $space): Response
    {
        if (! $this->sameOrganization($actor, $space->organization_id) || ! $actor->hasRole(Role::Student)) {
            return Response::deny();
        }

        return Gate::forUser($actor)->inspect('participate', $space);
    }

    /**
     * The group with its members and pending invitations.
     */
    public function view(User $actor, Group $group): bool
    {
        if (! $this->sameOrganization($actor, $group->organization_id)) {
            return false;
        }

        return match ($actor->role) {
            Role::Student => $this->participates($actor, $group)
                && ($group->hasMember($actor) || $group->hasInvited($actor)),
            Role::Teacher => $group->courseSpace->isOwnedBy($actor),
            Role::OrgAdmin => true,
        };
    }

    /**
     * Invite students and withdraw pending invitations.
     */
    public function invite(User $actor, Group $group): bool
    {
        return $this->isMember($actor, $group);
    }

    public function leave(User $actor, Group $group): bool
    {
        return $this->isMember($actor, $group);
    }

    /**
     * Rename.
     */
    public function update(User $actor, Group $group): bool
    {
        return $this->isOwner($actor, $group);
    }

    public function transferOwnership(User $actor, Group $group): bool
    {
        return $this->isOwner($actor, $group);
    }

    /**
     * Dissolve.
     */
    public function delete(User $actor, Group $group): bool
    {
        return $this->isOwner($actor, $group);
    }

    private function isOwner(User $actor, Group $group): bool
    {
        return $this->isMember($actor, $group) && $group->isOwnedBy($actor);
    }

    private function isMember(User $actor, Group $group): bool
    {
        return $this->sameOrganization($actor, $group->organization_id)
            && $actor->hasRole(Role::Student)
            && $this->participates($actor, $group)
            && $group->hasMember($actor);
    }

    private function participates(User $actor, Group $group): bool
    {
        return Gate::forUser($actor)->allows('participate', $group->courseSpace);
    }

    private function sameOrganization(User $actor, string $organizationId): bool
    {
        return $actor->canAct() && $actor->organization_id === $organizationId;
    }
}

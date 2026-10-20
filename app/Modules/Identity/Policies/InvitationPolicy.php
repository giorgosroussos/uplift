<?php

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;

/**
 * OWN-002: an org admin sees and revokes only invitations of their own
 * organisation. Nobody else sees or changes invitations.
 */
class InvitationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isActiveOrgAdmin($actor);
    }

    public function create(User $actor): bool
    {
        return $this->isActiveOrgAdmin($actor);
    }

    public function view(User $actor, Invitation $invitation): bool
    {
        return $this->grants($actor, $invitation);
    }

    public function revoke(User $actor, Invitation $invitation): bool
    {
        return $this->grants($actor, $invitation);
    }

    private function grants(User $actor, Invitation $invitation): bool
    {
        return $this->isActiveOrgAdmin($actor)
            && $actor->organization_id === $invitation->organization_id;
    }

    private function isActiveOrgAdmin(User $actor): bool
    {
        return $actor->canAct() && $actor->hasRole(Role::OrgAdmin);
    }
}

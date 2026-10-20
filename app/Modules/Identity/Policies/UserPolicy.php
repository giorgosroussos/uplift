<?php

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;

/**
 * OWN-001: a student or teacher reads and edits only their own user record;
 * an org admin manages only users of their own organisation.
 *
 * Role and activation (UC-032) are managed only by an org admin, never by the
 * user themselves unless they are that org admin.
 */
class UserPolicy
{
    /**
     * The user list of the organisation (UC-032).
     */
    public function viewAny(User $actor): bool
    {
        return $actor->canAct() && $actor->hasRole(Role::OrgAdmin);
    }

    /**
     * Change the role, deactivate or reactivate (UC-032).
     */
    public function manage(User $actor, User $user): bool
    {
        return $actor->canAct()
            && $actor->hasRole(Role::OrgAdmin)
            && $actor->organization_id === $user->organization_id;
    }

    public function view(User $actor, User $user): bool
    {
        return $this->grants($actor, $user);
    }

    public function update(User $actor, User $user): bool
    {
        return $this->grants($actor, $user);
    }

    public function delete(User $actor, User $user): bool
    {
        return $this->grants($actor, $user);
    }

    private function grants(User $actor, User $user): bool
    {
        if (! $actor->canAct()) {
            return false;
        }

        if ($actor->hasRole(Role::OrgAdmin)) {
            return $actor->organization_id === $user->organization_id;
        }

        return $actor->getKey() === $user->getKey()
            && $actor->organization_id === $user->organization_id;
    }
}

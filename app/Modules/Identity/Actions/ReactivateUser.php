<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Events\UserReactivated;
use App\Modules\Identity\Models\User;

/**
 * UC-032 step 3: an org admin reactivates a deactivated user, who can log in
 * again.
 */
final class ReactivateUser
{
    public function handle(User $actor, User $user): void
    {
        if ($user->is_active) {
            return;
        }

        $user->forceFill(['is_active' => true])->save();

        UserReactivated::dispatch($user->organization_id, (string) $user->getKey(), (string) $actor->getKey());
    }
}

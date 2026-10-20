<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Events\UserDeactivated;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\OrgAdminGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-032 step 3: an org admin deactivates a user. The user can no longer log
 * in and an open session ends on the next request (EnsureAccountIsActive).
 * The last active org admin cannot be deactivated. Dispatches
 * UserDeactivated (audited, SEC-010).
 */
final class DeactivateUser
{
    /**
     * @throws ValidationException when the user is the last active org admin
     */
    public function handle(User $actor, User $user): void
    {
        $changed = DB::transaction(function () use ($user): bool {
            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $locked->is_active) {
                return false;
            }

            OrgAdminGuard::ensureNotLastActiveAdmin($locked, 'user');

            $locked->forceFill(['is_active' => false])->save();

            return true;
        });

        if (! $changed) {
            return;
        }

        $user->refresh();

        UserDeactivated::dispatch($user->organization_id, (string) $user->getKey(), (string) $actor->getKey());
    }
}

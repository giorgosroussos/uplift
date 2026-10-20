<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Events\UserRoleChanged;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\OrgAdminGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-032 step 3: an org admin changes a user's role. The last active org
 * admin cannot be demoted. A user who stops being an org admin loses their
 * MFA in the same transaction (SEC-012). Dispatches UserRoleChanged
 * (audited, SEC-010).
 */
final class ChangeUserRole
{
    /**
     * @throws ValidationException when the user is the last active org admin
     */
    public function handle(User $actor, User $user, Role $role): void
    {
        /** @var Role|null $from */
        $from = DB::transaction(function () use ($user, $role): ?Role {
            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($locked->role === $role) {
                return null;
            }

            if ($role !== Role::OrgAdmin) {
                OrgAdminGuard::ensureNotLastActiveAdmin($locked, 'role');
            }

            $from = $locked->role;
            $changes = ['role' => $role];

            // MFA is offered only to org admins (SEC-011, SEC-012).
            if ($from === Role::OrgAdmin) {
                $changes += [
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_confirmed_at' => null,
                ];
            }

            $locked->forceFill($changes)->save();

            return $from;
        });

        if ($from === null) {
            return;
        }

        $user->refresh();

        UserRoleChanged::dispatch($user->organization_id, (string) $user->getKey(), (string) $actor->getKey(), $from->value, $role->value);
    }
}

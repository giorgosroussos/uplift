<?php

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * UC-032 exception: the last active org admin of an organisation can be
 * neither deactivated nor demoted, so an organisation is never left without
 * an admin.
 */
final class OrgAdminGuard
{
    /**
     * Must run inside a transaction: locks the organisation's active org
     * admins so that two admins cannot remove each other concurrently.
     *
     * @throws ValidationException when the user is the last active org admin
     */
    public static function ensureNotLastActiveAdmin(User $user, string $field): void
    {
        if (! $user->hasRole(Role::OrgAdmin) || ! $user->is_active) {
            return;
        }

        $activeAdmins = User::query()
            ->where('organization_id', $user->organization_id)
            ->where('role', Role::OrgAdmin->value)
            ->where('is_active', true)
            ->lockForUpdate()
            ->pluck('id');

        if ($activeAdmins->reject(fn (string $id): bool => $id === $user->getKey())->isEmpty()) {
            throw ValidationException::withMessages([
                $field => (string) __('users.errors.last_admin'),
            ]);
        }
    }
}

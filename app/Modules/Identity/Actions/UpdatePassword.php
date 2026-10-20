<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;

/**
 * UC-006 step 2: a user changes their own password after the current one was
 * checked (DATA-014). The new hash ends the user's other sessions: the
 * AuthenticateSession middleware of the web group logs out every session and
 * remember-me cookie that still carries the old hash (SEC-010), while the
 * current session is given the new hash at the end of this request.
 */
final class UpdatePassword
{
    public function handle(User $user, #[\SensitiveParameter] string $password): void
    {
        // Hashed with argon2id by the model's 'hashed' cast (SEC-010).
        $user->forceFill(['password' => $password])->save();
    }
}

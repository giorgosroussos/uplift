<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Concerns\PasswordValidationRules;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Sets a new password from a valid reset link. Other sessions of the user end
 * on their next request (AuthenticateSession compares the password hash) and
 * Fortify cycles the remember token (SEC-010).
 */
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();
    }
}

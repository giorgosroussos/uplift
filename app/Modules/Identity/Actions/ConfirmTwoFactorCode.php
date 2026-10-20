<?php

namespace App\Modules\Identity\Actions;

use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;

/**
 * UC-006 step 3: MFA becomes active only with a valid first TOTP code
 * (SEC-012). Fortify's action, with the error message translated
 * (SCOPE-020).
 */
class ConfirmTwoFactorCode extends ConfirmTwoFactorAuthentication
{
    /**
     * @param  mixed  $user
     * @param  string  $code
     *
     * @throws ValidationException
     */
    public function __invoke($user, $code)
    {
        try {
            parent::__invoke($user, $code);
        } catch (ValidationException) {
            throw ValidationException::withMessages([
                'code' => [(string) __('profile.two_factor.invalid_code')],
            ]);
        }
    }
}

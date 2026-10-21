<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\PlatformAdmin;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Completes the first-time MFA setup of a platform admin (SEC-011): the
 * secret becomes active only with a valid first TOTP code.
 */
class ConfirmTwoFactorSetup
{
    public function __construct(private readonly TwoFactorAuthenticationProvider $provider) {}

    /**
     * @throws ValidationException when the code is not valid
     */
    public function handle(PlatformAdmin $admin, #[\SensitiveParameter] string $code): void
    {
        $valid = $admin->two_factor_secret !== null
            && $admin->two_factor_confirmed_at === null
            && $code !== ''
            && $this->provider->verify(Fortify::currentEncrypter()->decrypt($admin->two_factor_secret), $code);

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => [(string) __('platform.two_factor.invalid_code')],
            ]);
        }

        $admin->forceFill(['two_factor_confirmed_at' => now()])->save();
    }
}

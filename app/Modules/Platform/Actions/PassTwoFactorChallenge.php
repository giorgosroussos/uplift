<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\PlatformAdmin;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * The TOTP step of a platform admin's login (SEC-011): a current TOTP code
 * (each code is accepted once) or one of the recovery codes, which is then
 * replaced so it cannot be used again.
 */
class PassTwoFactorChallenge
{
    public function __construct(private readonly TwoFactorAuthenticationProvider $provider) {}

    /**
     * @throws ValidationException when neither code is valid
     */
    public function handle(
        PlatformAdmin $admin,
        #[\SensitiveParameter] ?string $code,
        #[\SensitiveParameter] ?string $recoveryCode,
    ): void {
        if (! $admin->hasConfirmedTwoFactor()) {
            throw ValidationException::withMessages([
                'code' => [(string) __('platform.two_factor.invalid_code')],
            ]);
        }

        if ($recoveryCode !== null && $recoveryCode !== '') {
            $match = collect($admin->recoveryCodes())
                ->first(fn (mixed $stored): bool => is_string($stored) && hash_equals($stored, $recoveryCode));

            if (! is_string($match)) {
                throw ValidationException::withMessages([
                    'recovery_code' => [(string) __('platform.two_factor.invalid_recovery_code')],
                ]);
            }

            $admin->replaceRecoveryCode($match);

            return;
        }

        $valid = $code !== null && $code !== ''
            && $this->provider->verify(Fortify::currentEncrypter()->decrypt((string) $admin->two_factor_secret), $code);

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => [(string) __('platform.two_factor.invalid_code')],
            ]);
        }
    }
}

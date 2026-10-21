<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\PlatformAdmin;
use Illuminate\Support\Collection;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;

/**
 * First login of a platform admin without MFA (SEC-011): a TOTP secret and
 * recovery codes are generated, stored encrypted and stay unconfirmed until
 * the admin enters a valid first code. A confirmed secret is never replaced.
 */
class StartTwoFactorSetup
{
    public const int RECOVERY_CODES = 8;

    public function __construct(private readonly TwoFactorAuthenticationProvider $provider) {}

    public function handle(PlatformAdmin $admin): void
    {
        if ($admin->two_factor_secret !== null) {
            return;
        }

        $encrypter = Fortify::currentEncrypter();

        $admin->forceFill([
            'two_factor_secret' => $encrypter->encrypt($this->provider->generateSecretKey()),
            'two_factor_recovery_codes' => $encrypter->encrypt((string) json_encode(
                Collection::times(self::RECOVERY_CODES, fn (): string => RecoveryCode::generate())->all(),
            )),
            'two_factor_confirmed_at' => null,
        ])->save();
    }
}

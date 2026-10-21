<?php

namespace App\Modules\Platform\Database\Factories;

use App\Modules\Platform\Models\PlatformAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;

/**
 * Platform admins; ->withTwoFactor($secret) gives them confirmed MFA.
 *
 * @extends Factory<PlatformAdmin>
 */
class PlatformAdminFactory extends Factory
{
    protected $model = PlatformAdmin::class;

    /**
     * The password every factory admin has unless given another one.
     */
    public const string PASSWORD = 'platform-horse-battery';

    /**
     * Recovery codes of ->withTwoFactor().
     */
    public const array RECOVERY_CODES = ['recovery-code-one', 'recovery-code-two'];

    protected static ?string $passwordHash = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$passwordHash ??= Hash::make(self::PASSWORD),
        ];
    }

    /**
     * Confirmed TOTP MFA with the given secret.
     */
    public function withTwoFactor(string $secret): static
    {
        return $this->state(fn (): array => [
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt((string) json_encode(self::RECOVERY_CODES)),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}

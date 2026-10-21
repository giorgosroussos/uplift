<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Database\Factories\PlatformAdminFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * ENT-PlatformAdmin: a platform super-admin, outside tenancy and never a
 * member of an organisation (SEC-006). Authenticates with the `platform`
 * guard on the apex domain, always with TOTP MFA (SEC-011).
 *
 * name and email are personal data; password, two-factor secret and
 * recovery codes are secrets: none of them go to logs or error messages.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(PlatformAdminFactory::class)]
class PlatformAdmin extends Authenticatable
{
    /** @use HasFactory<PlatformAdminFactory> */
    use HasFactory;

    use HasUuids;
    use TwoFactorAuthenticatable;

    protected $table = 'platform_admins';

    /**
     * No "remember me" for platform admins.
     *
     * @var string
     */
    protected $rememberTokenName = '';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'name',
        'email',
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * MFA is required (SEC-011): only an admin with a confirmed TOTP secret
     * may act.
     */
    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtolower(trim($value)),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // argon2id (config/hashing.php, SEC-010).
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}

<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\UserFactory;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Identity\Notifications\VerifyEmailNotification;
use App\Modules\Identity\Support\NicknameGenerator;
use App\Modules\Identity\Support\OrganizationUrl;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use App\Modules\Tenancy\Contracts\OrganizationMember;
use App\Modules\Tenancy\Enums\Locale;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * ENT-User: a person in exactly one organisation with exactly one role
 * (SEC-004). Tenant-owned: queries only see users of the current tenant.
 *
 * Personal data (name, nickname, email, date_of_birth, is_minor), the
 * password hash and the two-factor secret and recovery codes never go to logs
 * or error messages. The two-factor secret and recovery codes are stored
 * encrypted by Fortify (SEC-012).
 *
 * @property string $id
 * @property string $organization_id
 * @property Role $role
 * @property string $name
 * @property string $nickname
 * @property string|null $avatar
 * @property string $email
 * @property string|null $password
 * @property Carbon|null $date_of_birth
 * @property bool|null $is_minor
 * @property Carbon|null $email_verified_at
 * @property bool $is_active
 * @property Locale|null $locale
 * @property string|null $remember_token
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 */
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, OrganizationMember
{
    use BelongsToOrganization;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuids;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'role',
        'name',
        'nickname',
        'avatar',
        'email',
        'password',
        'date_of_birth',
        'is_minor',
        'locale',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        // Runs after BelongsToOrganization has set organization_id.
        static::creating(function (User $user): void {
            if (blank($user->getAttribute('nickname'))) {
                $user->nickname = app(NicknameGenerator::class)->generate($user->organization_id);
            }
        });
    }

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    /**
     * OrganizationMember (ARCH-008): the user's one organisation (SEC-004).
     */
    public function organizationId(): string
    {
        return $this->organization_id;
    }

    /**
     * OrganizationMember (ARCH-008): org admins administer their organisation.
     */
    public function administersOrganization(): bool
    {
        return $this->hasRole(Role::OrgAdmin);
    }

    /**
     * A user acts only while both the account and its organisation are active.
     */
    public function canAct(): bool
    {
        return $this->is_active && $this->organization->isActive();
    }

    /**
     * What leaderboards and live broadcasts may show of a user (SEC-008,
     * SEC-010): the nickname and the preset avatar key, nothing else.
     *
     * @return array{id: string, nickname: string, avatar: string|null}
     */
    public function publicProfile(): array
    {
        return [
            'id' => (string) $this->getKey(),
            'nickname' => $this->nickname,
            'avatar' => $this->avatar,
        ];
    }

    public function preferredLocale(): string
    {
        return $this->locale->value ?? $this->organization->default_locale->value;
    }

    /**
     * The reset link points at the user's organisation subdomain; the mail is
     * queued (EXT-002).
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $url = OrganizationUrl::for($this->organization)->route('password.reset', [
            'token' => $token,
            'email' => $this->getEmailForPasswordReset(),
        ]);

        $this->notify(new ResetPasswordNotification($url));
    }

    /**
     * The verification link points at the user's organisation subdomain; the
     * mail is queued (EXT-002).
     */
    public function sendEmailVerificationNotification(): void
    {
        $url = OrganizationUrl::for($this->organization)->temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) Config::get('auth.verification.expire', 60)),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())],
        );

        $this->notify(new VerifyEmailNotification($url));
    }

    /**
     * Emails are compared case-insensitively: they are stored in lower case.
     *
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
            'role' => Role::class,
            'locale' => Locale::class,
            'date_of_birth' => 'date',
            'is_minor' => 'boolean',
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}

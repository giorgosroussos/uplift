<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Identity\Support\EqualWork;
use App\Modules\Platform\Models\PlatformAdmin;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The password step of a platform admin's login (SEC-006). At most 5 failed
 * attempts per minute per email (SEC-010); an unknown email costs the same
 * hashing work as a wrong password, and both give the same message.
 */
class AttemptPlatformLogin
{
    public const int MAX_ATTEMPTS = 5;

    public const int DECAY_SECONDS = 60;

    public function __construct(private readonly Hasher $hasher) {}

    /**
     * @throws ValidationException when throttled or the credentials are wrong
     */
    public function handle(string $email, #[\SensitiveParameter] string $password): PlatformAdmin
    {
        $email = mb_strtolower(trim($email));
        $key = 'platform-login|'.Str::transliterate($email);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => [(string) __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)])],
            ])->status(429);
        }

        $admin = PlatformAdmin::query()->where('email', $email)->first();

        $valid = $admin !== null
            ? $this->hasher->check($password, $admin->password)
            : EqualWork::check($this->hasher, $password);

        if (! $valid || $admin === null) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => [(string) __('auth.failed')],
            ]);
        }

        RateLimiter::clear($key);

        return $admin;
    }
}

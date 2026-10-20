<?php

namespace App\Modules\Identity\Support;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * SEC-010: at most 3 successful organisation registrations per hour per IP.
 * Only successful registrations count; submissions rejected by validation
 * do not.
 */
final class RegistrationRateLimiter
{
    public const int MAX_REGISTRATIONS = 3;

    public const int DECAY_SECONDS = 3600;

    /**
     * @throws ThrottleRequestsException (429) when the IP reached the limit
     */
    public function ensureNotExceeded(Request $request): void
    {
        $key = $this->key($request);

        if (RateLimiter::tooManyAttempts($key, self::MAX_REGISTRATIONS)) {
            throw new ThrottleRequestsException(
                (string) __('registration.too_many'),
                headers: ['Retry-After' => (string) RateLimiter::availableIn($key)],
            );
        }
    }

    public function recordRegistration(Request $request): void
    {
        RateLimiter::hit($this->key($request), self::DECAY_SECONDS);
    }

    private function key(Request $request): string
    {
        return 'register-organization:'.$request->ip();
    }
}

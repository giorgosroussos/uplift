<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Support\RegistrationRateLimiter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects a sign-up with 429 before validation once the IP has reached its
 * successful registrations (SEC-010); the controller records each success.
 */
final class ThrottleRegistrations
{
    public function __construct(private readonly RegistrationRateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->limiter->ensureNotExceeded($request);

        return $next($request);
    }
}

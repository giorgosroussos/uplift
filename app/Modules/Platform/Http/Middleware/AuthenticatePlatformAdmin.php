<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Support\PlatformSession;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only a platform admin logged in with the `platform` guard, after the
 * TOTP step (SEC-006, SEC-011). Organisation users never pass: their guard
 * and table are different. An admin whose MFA is no longer confirmed is
 * logged out.
 */
final class AuthenticatePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = PlatformSession::admin();

        if ($admin !== null && ! $admin->hasConfirmedTwoFactor()) {
            PlatformSession::logOut($request);
            $admin = null;
        }

        if ($admin === null) {
            throw new AuthenticationException(
                guards: [PlatformSession::GUARD],
                redirectTo: route('platform.login'),
            );
        }

        return $next($request);
    }
}

<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Support\PlatformSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The TOTP step needs a passed password step in the same session.
 */
final class RequirePendingPlatformLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PlatformSession::pending($request) === null) {
            return redirect()->route('platform.login');
        }

        return $next($request);
    }
}

<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Support\PlatformSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The login steps are for guests: a logged-in platform admin goes to the
 * platform dashboard.
 */
final class RedirectIfPlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PlatformSession::admin() !== null) {
            return redirect()->route('platform.dashboard');
        }

        return $next($request);
    }
}

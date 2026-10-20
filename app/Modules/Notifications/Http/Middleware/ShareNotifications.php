<?php

namespace App\Modules\Notifications\Http\Middleware;

use App\Modules\Notifications\Http\SharedNotifications;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the `notifications` prop with every Inertia page (DEC-002).
 *
 * Registered per request because shared props may be flushed between
 * requests (long-running workers, browser tests); added only once.
 */
final class ShareNotifications
{
    public function handle(Request $request, Closure $next): Response
    {
        $shared = Inertia::getShared();

        if (! collect(is_array($shared) ? $shared : [])->contains(fn (mixed $prop): bool => $prop instanceof SharedNotifications)) {
            Inertia::share(new SharedNotifications);
        }

        return $next($request);
    }
}

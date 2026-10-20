<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Http\SharedOrganization;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the `organization` prop with every Inertia page (DATA-011).
 *
 * Registered per request because shared props may be flushed between
 * requests (long-running workers, browser tests); added only once, since the
 * provider itself resolves the current tenant and locale when the page renders.
 */
final class ShareOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $shared = Inertia::getShared();

        if (! collect(is_array($shared) ? $shared : [])->contains(fn (mixed $prop): bool => $prop instanceof SharedOrganization)) {
            Inertia::share(new SharedOrganization);
        }

        return $next($request);
    }
}

<?php

namespace App\Modules\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform administration exists only on the apex domain itself (SEC-006):
 * on an organisation's subdomain, a reserved subdomain or any other host it
 * does not exist (404).
 */
final class RequireApexHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $apex = strtolower((string) config('tenancy.domain'));

        abort_if($apex === '' || strtolower($request->getHost()) !== $apex, 404);

        return $next($request);
    }
}

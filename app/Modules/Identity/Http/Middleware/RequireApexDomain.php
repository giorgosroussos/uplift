<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Tenancy\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The route exists only on the apex domain, never on an organisation's
 * subdomain (sign-up, UC-001).
 */
final class RequireApexDomain
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->tenant->check(), 404);

        return $next($request);
    }
}

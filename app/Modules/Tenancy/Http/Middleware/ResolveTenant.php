<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\Subdomain;
use App\Modules\Tenancy\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant from the request host before authentication.
 *
 * - the apex domain, a reserved subdomain or a host outside the apex domain:
 *   no tenant;
 * - <subdomain>.<apex> of an organisation: that organisation is the tenant;
 * - any other host under the apex domain: 404.
 */
final class ResolveTenant
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenant->clear();

        $label = $this->subdomainLabel(strtolower($request->getHost()));

        if ($label === null || Subdomain::isReserved($label)) {
            return $next($request);
        }

        $organization = Subdomain::isWellFormed($label)
            ? Organization::query()->where('subdomain', $label)->first()
            : null;

        abort_if($organization === null, 404);

        $this->tenant->set($organization);

        return $next($request);
    }

    /**
     * The part of the host before the apex domain, or null for the apex
     * domain itself and hosts outside it.
     */
    private function subdomainLabel(string $host): ?string
    {
        $apex = strtolower((string) config('tenancy.domain'));
        $suffix = '.'.$apex;

        if ($apex === '' || $host === $apex || ! str_ends_with($host, $suffix)) {
            return null;
        }

        return substr($host, 0, -strlen($suffix));
    }
}

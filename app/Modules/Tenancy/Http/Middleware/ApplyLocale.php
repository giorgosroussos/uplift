<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Enums\Locale;
use App\Modules\Tenancy\Support\TenantContext;
use Closure;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The UI locale of a request (DATA-011): the user's own locale; for guests
 * and users without one, the organisation's default_locale. Outside an
 * organisation (apex domain) the application default stays.
 */
final class ApplyLocale
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $organization = $this->tenant->current();

        if ($organization !== null) {
            $user = $request->user();
            $preferred = $user instanceof HasLocalePreference ? Locale::tryFrom((string) $user->preferredLocale()) : null;

            App::setLocale(($preferred ?? $organization->default_locale)->value);
        }

        return $next($request);
    }
}

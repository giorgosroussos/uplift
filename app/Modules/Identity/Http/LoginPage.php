<?php

namespace App\Modules\Identity\Http;

use App\Modules\Identity\Contracts\IdentityProviderResolver;
use App\Modules\Identity\Exceptions\IdentityProviderUnavailable;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * The login page of an organisation subdomain (UC-003). Email and password
 * login never depends on the identity providers (INT-002): when they are
 * unavailable the page shows an error and the password form still works.
 */
final class LoginPage
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly IdentityProviderResolver $identityProviders,
    ) {}

    public function __invoke(Request $request): Response
    {
        $organization = $this->tenant->current();

        $providers = [];
        $providersUnavailable = false;

        if ($organization !== null) {
            try {
                $providers = $this->identityProviders->providersFor((string) $organization->getKey());
            } catch (IdentityProviderUnavailable) {
                $providersUnavailable = true;
            }
        }

        return Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
            'organization' => $organization === null ? null : ['name' => $organization->name],
            'identityProviders' => $providers,
            'identityProvidersUnavailable' => $providersUnavailable,
        ]);
    }
}

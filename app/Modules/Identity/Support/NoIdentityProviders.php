<?php

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Contracts\IdentityProviderResolver;
use App\Modules\Identity\Models\User;

/**
 * IdentityProviderResolver while SSO is deferred (EXT-001): no organisation
 * has an identity provider, and no external system is called.
 */
final class NoIdentityProviders implements IdentityProviderResolver
{
    public function providersFor(string $organizationId): array
    {
        return [];
    }

    public function resolveUser(string $organizationId, string $provider, string $externalSubject): ?User
    {
        return null;
    }
}

<?php

namespace App\Modules\Identity\Contracts;

use App\Modules\Identity\Exceptions\IdentityProviderUnavailable;
use App\Modules\Identity\Models\User;

/**
 * INT-002: the only way into single sign-on identity providers (EXT-001).
 *
 * SSO itself is deferred; until then NoIdentityProviders is bound and no
 * organisation has a provider. Implementations MUST call a provider with a
 * timeout of 10 seconds (config identity.providers.timeout) and no retries
 * (interactive login), and throw IdentityProviderUnavailable when it fails
 * or times out. Login with email and password never depends on a provider.
 */
interface IdentityProviderResolver
{
    /**
     * Identity providers enabled for the organisation (provider keys).
     *
     * @return list<string>
     *
     * @throws IdentityProviderUnavailable
     */
    public function providersFor(string $organizationId): array;

    /**
     * The organisation's user linked to the provider's subject, if any.
     *
     * @throws IdentityProviderUnavailable
     */
    public function resolveUser(string $organizationId, string $provider, string $externalSubject): ?User;
}

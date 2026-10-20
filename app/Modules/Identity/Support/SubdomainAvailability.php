<?php

namespace App\Modules\Identity\Support;

use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\Subdomain;

/**
 * Whether a subdomain can be registered (UC-001), and if not, why: it is
 * invalid (3-30 characters a-z, 0-9, inner hyphens), reserved by the
 * platform, or already used by an organisation (case-insensitive).
 */
final class SubdomainAvailability
{
    public const string INVALID = 'invalid';

    public const string RESERVED = 'reserved';

    public const string TAKEN = 'taken';

    /**
     * The reason the subdomain cannot be registered, or null when it can.
     */
    public function unavailableReason(string $subdomain): ?string
    {
        if (! Subdomain::isWellFormed($subdomain)) {
            return self::INVALID;
        }

        if (Subdomain::isReserved($subdomain)) {
            return self::RESERVED;
        }

        if (Organization::query()->where('subdomain', Subdomain::normalize($subdomain))->exists()) {
            return self::TAKEN;
        }

        return null;
    }

    /**
     * Translated message for a reason, shown at the subdomain field.
     */
    public static function message(string $reason): string
    {
        return (string) __('registration.subdomain.'.$reason);
    }
}

<?php

namespace App\Modules\Tenancy\Support;

/**
 * Rules for organisation subdomains: 3-30 characters of a-z and 0-9 with
 * hyphens only inside, case-insensitive, and never one of the reserved
 * subdomains of the platform.
 */
final class Subdomain
{
    /**
     * Subdomains the platform keeps for itself; no organisation may use them
     * and requests to them never resolve a tenant.
     *
     * @var list<string>
     */
    public const array RESERVED = ['www', 'app', 'api', 'admin', 'mail'];

    public const string PATTERN = '/^[a-z0-9][a-z0-9-]{1,28}[a-z0-9]$/';

    public static function normalize(string $subdomain): string
    {
        return strtolower(trim($subdomain));
    }

    public static function isReserved(string $subdomain): bool
    {
        return in_array(self::normalize($subdomain), self::RESERVED, true);
    }

    public static function isWellFormed(string $subdomain): bool
    {
        return preg_match(self::PATTERN, self::normalize($subdomain)) === 1;
    }

    /**
     * Well-formed and not reserved (availability is a separate check).
     */
    public static function isAllowed(string $subdomain): bool
    {
        return self::isWellFormed($subdomain) && ! self::isReserved($subdomain);
    }
}

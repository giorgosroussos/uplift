<?php

namespace App\Modules\Platform\Support;

use App\Modules\Tenancy\Models\Organization;
use DateTimeInterface;

/**
 * Dates of an organisation as shown to platform admins (ISO 8601, UTC).
 */
final class OrganizationDates
{
    public static function createdAt(Organization $organization): ?string
    {
        $createdAt = $organization->getAttribute('created_at');

        return $createdAt instanceof DateTimeInterface ? $createdAt->format(DATE_ATOM) : null;
    }
}

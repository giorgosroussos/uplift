<?php

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Support\OrganizationScope;

/**
 * SEC-004: an email address belongs to at most one user of the whole
 * platform. Looks across organisations on purpose; it answers yes or no
 * and never exposes the other organisation or user.
 */
final class PlatformEmails
{
    public static function inUse(#[\SensitiveParameter] string $email): bool
    {
        return User::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('email', mb_strtolower(trim($email)))
            ->exists();
    }
}

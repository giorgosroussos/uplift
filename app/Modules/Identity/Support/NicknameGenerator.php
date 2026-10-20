<?php

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Support\OrganizationScope;
use RuntimeException;

/**
 * Auto-generated nicknames: shown instead of real names on leaderboards
 * until the user picks one, unique within the organisation (SEC-008).
 */
class NicknameGenerator
{
    private const int ATTEMPTS = 20;

    public function generate(string $organizationId): string
    {
        for ($i = 0; $i < self::ATTEMPTS; $i++) {
            $candidate = 'user-'.random_int(100000, 999999);

            if (! $this->taken($organizationId, $candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Could not generate a unique nickname.');
    }

    private function taken(string $organizationId, string $nickname): bool
    {
        return User::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where('nickname', $nickname)
            ->exists();
    }
}

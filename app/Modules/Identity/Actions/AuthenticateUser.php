<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\EqualWork;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Str;

/**
 * Email and password login on the organisation subdomain (UC-003).
 *
 * Only users of the current organisation are looked up, so a user of another
 * organisation fails exactly like a wrong password. Deactivated users and
 * users of a suspended organisation are refused.
 *
 * Fortify's custom-callback login skips the guard's Timebox, so equal work is
 * enforced here: unknown emails and users without a password are checked
 * against a dummy hash of the same cost.
 */
class AuthenticateUser
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly Hasher $hasher,
    ) {}

    public function handle(string $email, #[\SensitiveParameter] string $password): ?User
    {
        if (! $this->tenant->check()) {
            return null;
        }

        $user = User::query()->where('email', Str::lower(trim($email)))->first();

        // Every path pays exactly one password check, so the response time
        // does not tell whether the email has an account here.
        $valid = $user?->password !== null
            ? $this->hasher->check($password, $user->password)
            : EqualWork::check($this->hasher, $password);

        if (! $valid || $user === null || ! $user->canAct()) {
            return null;
        }

        return $user;
    }
}

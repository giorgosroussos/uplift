<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Email and password login on the organisation subdomain (UC-003).
 *
 * Only users of the current organisation are looked up, so a user of another
 * organisation fails exactly like a wrong password. Deactivated users and
 * users of a suspended organisation are refused.
 */
class AuthenticateUser
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(string $email, #[\SensitiveParameter] string $password): ?User
    {
        if (! $this->tenant->check()) {
            return null;
        }

        $user = User::query()->where('email', Str::lower(trim($email)))->first();

        if ($user === null || $user->password === null || ! Hash::check($password, $user->password)) {
            return null;
        }

        if (! $user->canAct()) {
            return null;
        }

        return $user;
    }
}

<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\PlatformAdmin;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Creates a platform admin (SEC-006), from the console only. The admin sets
 * up the required TOTP MFA at the first login (SEC-011).
 */
class CreatePlatformAdmin
{
    /**
     * @throws ValidationException
     */
    public function handle(string $name, string $email, #[\SensitiveParameter] string $password): PlatformAdmin
    {
        $email = mb_strtolower(trim($email));

        Validator::make(
            ['name' => trim($name), 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:254', Rule::unique(PlatformAdmin::class, 'email')],
                // SEC-010: at least 8 characters, not known to be compromised.
                'password' => ['required', 'string', Password::defaults()],
            ],
        )->validate();

        return PlatformAdmin::query()->create([
            'name' => trim($name),
            'email' => $email,
            'password' => $password,
        ]);
    }
}

<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Concerns\PasswordValidationRules;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * UC-006: the user changes their own password with the current one
 * (DATA-014, SEC-010).
 */
class UpdatePasswordRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('update', $user);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => (string) __('profile.attributes.current_password'),
            'password' => (string) __('profile.attributes.password'),
        ];
    }

    public function newPassword(): string
    {
        return (string) $this->validated('password');
    }
}

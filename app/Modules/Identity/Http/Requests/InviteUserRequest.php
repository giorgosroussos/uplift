<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Invitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UC-032: invite an email address with a role.
 */
class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Invitation::class);
    }

    /**
     * @return array<string, array<int, ValidationRule|string|object>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:254'],
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => (string) __('users.attributes.email'),
            'role' => (string) __('users.attributes.role'),
        ];
    }

    public function email(): string
    {
        return (string) $this->validated('email');
    }

    public function role(): Role
    {
        return Role::from((string) $this->validated('role'));
    }
}

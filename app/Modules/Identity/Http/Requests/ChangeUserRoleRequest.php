<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UC-032: change a user's role.
 */
class ChangeUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User && (bool) $this->user()?->can('manage', $user);
    }

    /**
     * @return array<string, array<int, ValidationRule|string|object>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'role' => (string) __('users.attributes.role'),
        ];
    }

    public function role(): Role
    {
        return Role::from((string) $this->validated('role'));
    }
}

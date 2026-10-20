<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UC-032: filters of the user list.
 */
class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', User::class);
    }

    /**
     * @return array<string, array<int, ValidationRule|string|object>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::enum(Role::class)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{search?: string|null, role?: string|null, status?: string|null}
     */
    public function filters(): array
    {
        /** @var array{search?: string|null, role?: string|null, status?: string|null} $filters */
        $filters = $this->safe()->only(['search', 'role', 'status']);

        return $filters;
    }
}

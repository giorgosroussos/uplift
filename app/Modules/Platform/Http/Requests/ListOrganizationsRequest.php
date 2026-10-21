<?php

namespace App\Modules\Platform\Http\Requests;

use App\Modules\Tenancy\Enums\OrganizationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UC-036: filters of the platform's organisation list. Only platform admins
 * reach it (AuthenticatePlatformAdmin on the route).
 */
class ListOrganizationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string|object>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(OrganizationStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{search?: string|null, status?: string|null}
     */
    public function filters(): array
    {
        /** @var array{search?: string|null, status?: string|null} $filters */
        $filters = $this->safe()->only(['search', 'status']);

        return $filters;
    }
}

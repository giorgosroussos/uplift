<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Concerns\PasswordValidationRules;
use App\Modules\Identity\Rules\AvailableSubdomain;
use App\Modules\Identity\Support\SubdomainAvailability;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * UC-001 sign-up form: organisation name and subdomain, admin name, email
 * and password. None of the organisation settings is asked (DATA-010).
 */
class RegisterOrganizationRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string|object>>
     */
    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:255'],
            'subdomain' => ['bail', 'required', 'string', new AvailableSubdomain(new SubdomainAvailability)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'organization_name' => (string) __('registration.attributes.organization_name'),
            'subdomain' => (string) __('registration.attributes.subdomain'),
            'name' => (string) __('registration.attributes.name'),
            'email' => (string) __('registration.attributes.email'),
            'password' => (string) __('registration.attributes.password'),
        ];
    }

    /**
     * @return array{organization_name: string, subdomain: string, name: string, email: string, password: string}
     */
    public function registration(): array
    {
        /** @var array{organization_name: string, subdomain: string, name: string, email: string, password: string} $input */
        $input = $this->safe()->only(['organization_name', 'subdomain', 'name', 'email', 'password']);

        return $input;
    }
}

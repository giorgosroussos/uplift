<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * UC-005: the invitee sets their name and password. The token in the link
 * is checked by the action.
 */
class AcceptInvitationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => (string) __('invitations.attributes.name'),
            'password' => (string) __('invitations.attributes.password'),
        ];
    }

    /**
     * @return array{name: string, password: string}
     */
    public function account(): array
    {
        /** @var array{name: string, password: string} $input */
        $input = $this->safe()->only(['name', 'password']);

        return $input;
    }
}

<?php

namespace App\Modules\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A TOTP code, or (at the login challenge only) a recovery code.
 */
class TwoFactorCodeRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $recovery = $this->routeIs('platform.two-factor.challenge.store');

        return [
            'code' => $recovery
                ? ['nullable', 'string', 'max:16', 'required_without:recovery_code']
                : ['required', 'string', 'max:16'],
            'recovery_code' => $recovery
                ? ['nullable', 'string', 'max:64']
                : ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => (string) __('platform.attributes.code'),
            'recovery_code' => (string) __('platform.attributes.recovery_code'),
        ];
    }

    public function code(): ?string
    {
        $code = $this->validated('code');

        return is_string($code) ? $code : null;
    }

    public function recoveryCode(): ?string
    {
        $code = $this->validated('recovery_code');

        return is_string($code) ? $code : null;
    }
}

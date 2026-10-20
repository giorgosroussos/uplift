<?php

namespace App\Modules\Identity\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedTwoFactorLoginResponse as FailedTwoFactorLoginResponseContract;

/**
 * A wrong code or recovery code at the two-factor challenge (SEC-012), with
 * the message translated (SCOPE-020).
 */
final class FailedTwoFactorLoginResponse implements FailedTwoFactorLoginResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        [$key, $message] = $request->filled('recovery_code')
            ? ['recovery_code', (string) __('profile.two_factor.invalid_recovery_code')]
            : ['code', (string) __('profile.two_factor.invalid_code')];

        if ($request->wantsJson()) {
            throw ValidationException::withMessages([$key => [$message]]);
        }

        return redirect()->route('two-factor.login')->withErrors([$key => $message]);
    }
}

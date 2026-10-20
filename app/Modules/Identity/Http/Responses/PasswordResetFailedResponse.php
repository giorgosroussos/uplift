<?php

namespace App\Modules\Identity\Http\Responses;

use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A failed password reset always reports an invalid link, whether the email
 * is unknown or the token is wrong, expired or used: the response never
 * reveals which emails have accounts (UC-004). Same shape as Fortify's.
 */
final class PasswordResetFailedResponse implements FailedPasswordResetResponse
{
    /**
     * The broker status Fortify passes in is deliberately not used.
     */
    public function toResponse($request): Response
    {
        $message = trans(Password::INVALID_TOKEN);

        if ($request->wantsJson()) {
            throw ValidationException::withMessages(['email' => [$message]]);
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => $message]);
    }
}

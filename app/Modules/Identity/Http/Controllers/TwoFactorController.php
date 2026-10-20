<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;

/**
 * UC-006 step 3: an org admin's optional MFA (SEC-012). The route is
 * authorised (manageTwoFactor) and behind password confirmation; enabling,
 * confirming and disabling are Fortify's routes, restricted to org admins by
 * RestrictTwoFactorManagement.
 */
class TwoFactorController extends Controller
{
    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $enabled = $user->hasEnabledTwoFactorAuthentication();
        $pending = ! $enabled && $user->two_factor_secret !== null;

        return Inertia::render('Identity/Settings/TwoFactor', [
            'enabled' => $enabled,
            'pending' => $pending,
            'qrCode' => $pending ? 'data:image/svg+xml;base64,'.base64_encode($user->twoFactorQrCodeSvg()) : null,
            'setupKey' => $pending ? Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret) : null,
            // Loaded only when the admin asks to see them.
            'recoveryCodes' => Inertia::optional(fn (): array => $enabled ? $user->recoveryCodes() : []),
        ]);
    }
}

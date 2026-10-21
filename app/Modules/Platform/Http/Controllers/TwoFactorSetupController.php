<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Actions\ConfirmTwoFactorSetup;
use App\Modules\Platform\Actions\StartTwoFactorSetup;
use App\Modules\Platform\Http\Requests\TwoFactorCodeRequest;
use App\Modules\Platform\Models\PlatformAdmin;
use App\Modules\Platform\Support\PlatformSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;

/**
 * MFA is required for platform admins (SEC-011): an admin without it sets
 * it up right after the password step, before entering the platform.
 */
class TwoFactorSetupController extends Controller
{
    public function show(Request $request, StartTwoFactorSetup $start): Response|RedirectResponse
    {
        $admin = $this->pendingAdmin($request);

        if ($admin->hasConfirmedTwoFactor()) {
            return redirect()->route('platform.two-factor.challenge');
        }

        $start->handle($admin);

        return Inertia::render('Platform/TwoFactorSetup', [
            'qrCode' => 'data:image/svg+xml;base64,'.base64_encode($admin->twoFactorQrCodeSvg()),
            'setupKey' => Fortify::currentEncrypter()->decrypt((string) $admin->two_factor_secret),
        ]);
    }

    public function store(TwoFactorCodeRequest $request, ConfirmTwoFactorSetup $confirm): RedirectResponse
    {
        $admin = $this->pendingAdmin($request);

        if ($admin->hasConfirmedTwoFactor()) {
            return redirect()->route('platform.two-factor.challenge');
        }

        $confirm->handle($admin, (string) $request->code());

        PlatformSession::logIn($request, $admin);
        PlatformSession::offerRecoveryCodes($request);

        return redirect()->route('platform.recovery-codes');
    }

    private function pendingAdmin(Request $request): PlatformAdmin
    {
        // RequirePendingPlatformLogin guarantees one.
        return PlatformSession::pending($request) ?? abort(403);
    }
}

<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Actions\PassTwoFactorChallenge;
use App\Modules\Platform\Http\Requests\TwoFactorCodeRequest;
use App\Modules\Platform\Models\PlatformAdmin;
use App\Modules\Platform\Support\PlatformSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The TOTP step of a platform admin with MFA (SEC-011), limited to 5
 * attempts per minute per login session.
 */
class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $this->pendingAdmin($request)->hasConfirmedTwoFactor()) {
            return redirect()->route('platform.two-factor.setup');
        }

        return Inertia::render('Platform/TwoFactorChallenge');
    }

    public function store(TwoFactorCodeRequest $request, PassTwoFactorChallenge $challenge): RedirectResponse
    {
        $admin = $this->pendingAdmin($request);

        $challenge->handle($admin, $request->code(), $request->recoveryCode());

        PlatformSession::logIn($request, $admin);

        return redirect()->intended(route('platform.dashboard'));
    }

    private function pendingAdmin(Request $request): PlatformAdmin
    {
        // RequirePendingPlatformLogin guarantees one.
        return PlatformSession::pending($request) ?? abort(403);
    }
}

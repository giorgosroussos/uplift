<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Actions\AttemptPlatformLogin;
use App\Modules\Platform\Http\Requests\PlatformLoginRequest;
use App\Modules\Platform\Support\PlatformSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Login and logout of platform admins on the apex domain (SEC-006). The
 * password step leads to the TOTP challenge, or to the MFA setup when the
 * admin has none yet (SEC-011): never directly into the platform.
 */
class PlatformLoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Platform/Login');
    }

    public function store(PlatformLoginRequest $request, AttemptPlatformLogin $attempt): RedirectResponse
    {
        $admin = $attempt->handle((string) $request->validated('email'), (string) $request->validated('password'));

        PlatformSession::startPending($request, $admin);

        return redirect()->route($admin->hasConfirmedTwoFactor()
            ? 'platform.two-factor.challenge'
            : 'platform.two-factor.setup');
    }

    public function destroy(Request $request): RedirectResponse
    {
        PlatformSession::logOut($request);

        return redirect()->route('platform.login');
    }
}

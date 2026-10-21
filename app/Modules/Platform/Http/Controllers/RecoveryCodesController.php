<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Support\PlatformSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The recovery codes of a just confirmed MFA, shown once (SEC-011).
 */
class RecoveryCodesController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $admin = PlatformSession::admin();

        if ($admin === null || ! PlatformSession::takeRecoveryCodesOffer($request)) {
            return redirect()->route('platform.dashboard');
        }

        return Inertia::render('Platform/RecoveryCodes', [
            'recoveryCodes' => $admin->recoveryCodes(),
        ]);
    }
}

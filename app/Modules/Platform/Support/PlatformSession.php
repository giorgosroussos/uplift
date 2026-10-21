<?php

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Models\PlatformAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The platform admin's session on the apex domain (SEC-006).
 *
 * Login has two steps: the password step only marks the admin as pending;
 * the `platform` guard logs them in once the TOTP step (challenge, or the
 * first-time setup) has passed (SEC-011).
 */
final class PlatformSession
{
    public const string GUARD = 'platform';

    private const string PENDING_KEY = 'platform.login.id';

    private const string SHOW_RECOVERY_CODES_KEY = 'platform.show_recovery_codes';

    /**
     * The password step passed: the admin still needs the TOTP step.
     */
    public static function startPending(Request $request, PlatformAdmin $admin): void
    {
        $request->session()->regenerate();
        $request->session()->put(self::PENDING_KEY, $admin->getKey());
    }

    /**
     * The admin who passed the password step in this session, if any.
     */
    public static function pending(Request $request): ?PlatformAdmin
    {
        $id = $request->session()->get(self::PENDING_KEY);

        return is_string($id) ? PlatformAdmin::query()->find($id) : null;
    }

    /**
     * The TOTP step passed: the admin is logged in with the platform guard.
     */
    public static function logIn(Request $request, PlatformAdmin $admin): void
    {
        $request->session()->forget(self::PENDING_KEY);
        Auth::guard(self::GUARD)->login($admin);
        $request->session()->regenerate();
    }

    public static function admin(): ?PlatformAdmin
    {
        $admin = Auth::guard(self::GUARD)->user();

        return $admin instanceof PlatformAdmin ? $admin : null;
    }

    public static function logOut(Request $request): void
    {
        Auth::guard(self::GUARD)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * The recovery codes of a just confirmed MFA are shown once.
     */
    public static function offerRecoveryCodes(Request $request): void
    {
        $request->session()->put(self::SHOW_RECOVERY_CODES_KEY, true);
    }

    public static function takeRecoveryCodesOffer(Request $request): bool
    {
        return $request->session()->pull(self::SHOW_RECOVERY_CODES_KEY, false) === true;
    }
}

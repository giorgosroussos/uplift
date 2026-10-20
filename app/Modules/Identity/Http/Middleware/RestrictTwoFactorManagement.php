<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEC-012: only org admins enable, confirm or disable MFA or see its secret,
 * QR code and recovery codes; other roles get 403. Runs on every Fortify
 * route (config/fortify.php) and acts on the MFA management routes only;
 * the login challenge stays open to the user being challenged.
 */
final class RestrictTwoFactorManagement
{
    /**
     * Fortify's MFA management routes.
     */
    public const array ROUTES = [
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'two-factor.recovery-codes',
        'two-factor.regenerate-recovery-codes',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Guests are sent to the login page by the route's auth middleware.
        if ($user !== null && in_array($request->route()?->getName(), self::ROUTES, true)) {
            abort_unless($user->can('manageTwoFactor', User::class), 403);
        }

        return $next($request);
    }
}

<?php

use App\Modules\Platform\Http\Controllers\PlatformDashboardController;
use App\Modules\Platform\Http\Controllers\PlatformLoginController;
use App\Modules\Platform\Http\Controllers\RecoveryCodesController;
use App\Modules\Platform\Http\Controllers\TwoFactorChallengeController;
use App\Modules\Platform\Http\Controllers\TwoFactorSetupController;
use App\Modules\Platform\Http\Middleware\AuthenticatePlatformAdmin;
use App\Modules\Platform\Http\Middleware\RedirectIfPlatformAdmin;
use App\Modules\Platform\Http\Middleware\RequireApexHost;
use App\Modules\Platform\Http\Middleware\RequirePendingPlatformLogin;
use Illuminate\Support\Facades\Route;

/*
| Platform administration (web middleware group): only on the apex domain,
| with the separate `platform` guard (SEC-006) and required TOTP MFA
| (SEC-011).
*/

Route::middleware(RequireApexHost::class)->prefix('admin')->name('platform.')->group(function () {
    Route::middleware(RedirectIfPlatformAdmin::class)->group(function () {
        Route::get('login', [PlatformLoginController::class, 'create'])->name('login');
        Route::post('login', [PlatformLoginController::class, 'store'])->name('login.store');

        Route::middleware(RequirePendingPlatformLogin::class)->group(function () {
            Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
                ->name('two-factor.challenge');
            Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
                ->middleware('throttle:platform-two-factor')
                ->name('two-factor.challenge.store');

            Route::get('two-factor-setup', [TwoFactorSetupController::class, 'show'])
                ->name('two-factor.setup');
            Route::post('two-factor-setup', [TwoFactorSetupController::class, 'store'])
                ->middleware('throttle:platform-two-factor')
                ->name('two-factor.setup.store');
        });
    });

    Route::middleware(AuthenticatePlatformAdmin::class)->group(function () {
        Route::get('/', PlatformDashboardController::class)->name('dashboard');
        Route::get('recovery-codes', RecoveryCodesController::class)->name('recovery-codes');
        Route::post('logout', [PlatformLoginController::class, 'destroy'])->name('logout');
    });
});

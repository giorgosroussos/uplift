<?php

use App\Modules\Identity\Http\Controllers\AcceptInvitationController;
use App\Modules\Identity\Http\Controllers\DashboardController;
use App\Modules\Identity\Http\Controllers\InvitationController;
use App\Modules\Identity\Http\Controllers\PasswordController;
use App\Modules\Identity\Http\Controllers\ProfileController;
use App\Modules\Identity\Http\Controllers\RegistrationController;
use App\Modules\Identity\Http\Controllers\SubdomainCheckController;
use App\Modules\Identity\Http\Controllers\TwoFactorController;
use App\Modules\Identity\Http\Controllers\UserController;
use App\Modules\Identity\Http\Middleware\RequireApexDomain;
use App\Modules\Identity\Http\Middleware\ThrottleRegistrations;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Route;

/*
| Identity routes (web middleware group). Login, logout, password reset and
| email verification are Fortify's routes, configured in IdentityServiceProvider.
*/

Route::middleware(['tenant', 'auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // User management of the org admin (UC-032).
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->whereUuid('user')->name('users.role');
    Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->whereUuid('user')->name('users.deactivate');
    Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->whereUuid('user')->name('users.reactivate');

    Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::post('invitations/{invitation}/revoke', [InvitationController::class, 'revoke'])->whereUuid('invitation')->name('invitations.revoke');

    // The user's own settings (UC-006): no user id, only ever one's own record.
    Route::redirect('settings', 'settings/profile')->name('settings');
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('settings.profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('settings.profile.update');
    Route::get('settings/password', [PasswordController::class, 'edit'])->name('settings.password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('settings.password.update');

    // MFA of org admins (SEC-012): authorised before the password is asked.
    Route::get('settings/two-factor', [TwoFactorController::class, 'show'])
        ->middleware(['can:manageTwoFactor,'.User::class, 'password.confirm'])
        ->name('settings.two-factor.show');
});

// Accepting an invitation (UC-005), from the link in the invitation email.
Route::middleware(['tenant', 'guest'])->group(function () {
    Route::get('invitation/{invitation}/{token}', [AcceptInvitationController::class, 'show'])
        ->whereUuid('invitation')
        ->name('invitation.show');
    Route::post('invitation/{invitation}/{token}', [AcceptInvitationController::class, 'store'])
        ->whereUuid('invitation')
        ->name('invitation.accept');
});

// Organisation sign-up (UC-001), only on the apex domain.
Route::middleware(RequireApexDomain::class)->group(function () {
    Route::get('register', [RegistrationController::class, 'create'])->name('register');
    Route::post('register', [RegistrationController::class, 'store'])
        ->middleware(ThrottleRegistrations::class)
        ->name('register.store');
    Route::get('register/subdomain', SubdomainCheckController::class)
        ->middleware('throttle:subdomain-check')
        ->name('register.subdomain');
});

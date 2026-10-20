<?php

use App\Modules\Identity\Http\Controllers\AcceptInvitationController;
use App\Modules\Identity\Http\Controllers\DashboardController;
use App\Modules\Identity\Http\Controllers\InvitationController;
use App\Modules\Identity\Http\Controllers\RegistrationController;
use App\Modules\Identity\Http\Controllers\SubdomainCheckController;
use App\Modules\Identity\Http\Controllers\UserController;
use App\Modules\Identity\Http\Middleware\RequireApexDomain;
use App\Modules\Identity\Http\Middleware\ThrottleRegistrations;
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

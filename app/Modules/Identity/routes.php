<?php

use App\Modules\Identity\Http\Controllers\DashboardController;
use App\Modules\Identity\Http\Controllers\RegistrationController;
use App\Modules\Identity\Http\Controllers\SubdomainCheckController;
use App\Modules\Identity\Http\Middleware\RequireApexDomain;
use App\Modules\Identity\Http\Middleware\ThrottleRegistrations;
use Illuminate\Support\Facades\Route;

/*
| Identity routes (web middleware group). Login, logout, password reset and
| email verification are Fortify's routes, configured in IdentityServiceProvider.
*/

Route::middleware(['tenant', 'auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
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

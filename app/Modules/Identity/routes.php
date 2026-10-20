<?php

use App\Modules\Identity\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
| Identity routes (web middleware group). Login, logout, password reset and
| email verification are Fortify's routes, configured in IdentityServiceProvider.
*/

Route::middleware(['tenant', 'auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

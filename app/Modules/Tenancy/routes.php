<?php

use App\Modules\Tenancy\Http\Controllers\OrganizationLogoController;
use App\Modules\Tenancy\Http\Controllers\OrganizationSettingsController;
use Illuminate\Support\Facades\Route;

/*
| Tenancy routes (web middleware group), on organisation subdomains only.
*/

// The organisation logo (DATA-011), also for guests: the login page shows it.
Route::middleware('tenant')
    ->get('organization/logo', OrganizationLogoController::class)
    ->name('organization.logo');

// Organisation settings of the org admin (UC-033).
Route::middleware(['tenant', 'auth', 'verified'])->group(function () {
    Route::get('organization/settings', [OrganizationSettingsController::class, 'edit'])->name('organization.settings.edit');
    Route::post('organization/settings', [OrganizationSettingsController::class, 'update'])->name('organization.settings.update');
});

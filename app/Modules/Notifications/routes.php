<?php

use App\Modules\Notifications\Http\Controllers\NotificationController;
use App\Modules\Notifications\Http\Controllers\NotificationSettingsController;
use Illuminate\Support\Facades\Route;

/*
| Notifications routes (web middleware group), on organisation subdomains
| only. {notification} binds only rows of the current tenant; the policy
| limits every route to the user's own notifications (DEC-002).
*/

Route::middleware(['tenant', 'auth', 'verified'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('notifications/{notification}/unread', [NotificationController::class, 'unread'])->name('notifications.unread');

    Route::get('settings/notifications', [NotificationSettingsController::class, 'edit'])->name('notifications.settings.edit');
    Route::put('settings/notifications', [NotificationSettingsController::class, 'update'])->name('notifications.settings.update');
});

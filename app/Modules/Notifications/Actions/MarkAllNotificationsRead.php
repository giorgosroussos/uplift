<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\Notification;

/**
 * UC-007: mark all of the user's own notifications read.
 */
class MarkAllNotificationsRead
{
    public function handle(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }
}

<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Models\Notification;

/**
 * UC-007: mark one notification read or unread.
 */
class MarkNotification
{
    public function handle(Notification $notification, bool $read): void
    {
        if ($notification->isRead() === $read) {
            return;
        }

        $notification->read_at = $read ? now() : null;
        $notification->save();
    }
}

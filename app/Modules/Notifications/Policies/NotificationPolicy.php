<?php

namespace App\Modules\Notifications\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\Notification;

/**
 * DEC-002: a user reads and changes only their own notifications, in their
 * own organisation (SEC-004).
 */
class NotificationPolicy
{
    public function update(User $actor, Notification $notification): bool
    {
        return $actor->canAct()
            && $notification->organization_id === $actor->organization_id
            && $notification->user_id === $actor->getKey();
    }
}

<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\NotificationPreference;

/**
 * DEC-002: the user turns their own DEC-001 notification emails on or off.
 * Account emails and in-app notifications are not affected.
 */
class UpdateNotificationPreference
{
    public function handle(User $user, bool $emailEnabled): NotificationPreference
    {
        return NotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            ['email_enabled' => $emailEnabled],
        );
    }
}

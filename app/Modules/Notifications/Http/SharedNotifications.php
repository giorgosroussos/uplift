<?php

namespace App\Modules\Notifications\Http;

use App\Modules\Notifications\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Inertia\ProvidesInertiaProperties;
use Inertia\RenderContext;

/**
 * The Inertia shared prop `notifications.unread_count` (DEC-002): the
 * signed-in user's unread notifications, for the bell in the app header.
 * Refreshed on every page load (no live broadcast); null for guests.
 */
final class SharedNotifications implements ProvidesInertiaProperties
{
    /**
     * @return array<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        $userId = Auth::id();

        if ($userId === null) {
            return ['notifications' => null];
        }

        return [
            'notifications' => [
                'unread_count' => Notification::query()
                    ->where('user_id', $userId)
                    ->whereNull('read_at')
                    ->count(),
            ],
        ];
    }
}

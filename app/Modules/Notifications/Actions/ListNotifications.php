<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\Notification;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * UC-007: the user's own notifications, newest first, 20 per page. The page
 * groups them by day.
 */
class ListNotifications
{
    public const int PER_PAGE = 20;

    /**
     * @return LengthAwarePaginator<int, array{id: string, kind: string, data: array<string, mixed>, read: bool, created_at: string|null}>
     */
    public function handle(User $user): LengthAwarePaginator
    {
        return Notification::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Notification $notification): array => [
                'id' => $notification->id,
                'kind' => $notification->kind->value,
                'data' => $notification->data,
                'read' => $notification->isRead(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]);
    }
}

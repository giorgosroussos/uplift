<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Enums\NotificationKind;
use App\Modules\Notifications\Models\Notification;
use Illuminate\Support\Facades\Gate;

/**
 * UC-007: opening a notification marks it read and returns its target. The
 * target obeys the normal access rules (DEC-002): an announcement_published
 * entry opens its course space while the user may read its announcements;
 * otherwise (archived, unenrolled, gone) there is no target.
 */
class OpenNotification
{
    public function __construct(private readonly MarkNotification $mark) {}

    /**
     * @return string|null the target path, null when the user may not open it
     */
    public function handle(User $user, Notification $notification): ?string
    {
        $this->mark->handle($notification, read: true);

        return match ($notification->kind) {
            NotificationKind::AnnouncementPublished => $this->courseSpace($user, $notification),
        };
    }

    private function courseSpace(User $user, Notification $notification): ?string
    {
        $space = CourseSpace::query()->find($notification->data['course_space_id'] ?? null);

        if ($space === null || Gate::forUser($user)->denies('viewAnnouncements', $space)) {
            return null;
        }

        return route('courses.show', $space, absolute: false);
    }
}

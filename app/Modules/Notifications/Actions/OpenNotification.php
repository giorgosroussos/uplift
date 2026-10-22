<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Enums\NotificationKind;
use App\Modules\Notifications\Models\Notification;
use Illuminate\Support\Facades\Gate;

/**
 * UC-007: opening a notification marks it read and returns its target. The
 * target obeys the normal access rules (DEC-002): an announcement_published
 * entry opens its course space while the user may read its announcements,
 * an activity_published entry its activity while the user may view it, a
 * grade_finalised or grade_changed entry the attempt's result while the
 * user may view it;
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
            NotificationKind::ActivityPublished => $this->activity($user, $notification),
            NotificationKind::GradeFinalised, NotificationKind::GradeChanged => $this->result($user, $notification),
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

    private function activity(User $user, Notification $notification): ?string
    {
        $activity = Activity::query()->find($notification->data['activity_id'] ?? null);

        if ($activity === null || Gate::forUser($user)->denies('view', $activity)) {
            return null;
        }

        return route('activities.show', $activity, absolute: false);
    }

    private function result(User $user, Notification $notification): ?string
    {
        $attempt = QuizAttempt::query()->find($notification->data['attempt_id'] ?? null);

        if ($attempt === null || Gate::forUser($user)->denies('viewResult', $attempt)) {
            return null;
        }

        return route('assessment.results.show', $attempt, absolute: false);
    }
}

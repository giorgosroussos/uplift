<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Courses\Models\Announcement;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\OrganizationUrl;
use App\Modules\Notifications\Enums\NotificationKind;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Notifications\AnnouncementPublishedEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notifier;
use Throwable;

/**
 * DEC-002: one in-app announcement_published notification per student
 * enrolled in the course space when the announcement is posted (author
 * excluded), with the space and announcement titles as they are now. Then a
 * queued DEC-001 email to each of them who has not turned emails off, in the
 * user's locale, else the organisation's default_locale.
 *
 * The in-app entries are written first and synchronously, so they exist even
 * when queueing or sending the email fails; a failing email never blocks
 * the teacher's post (INT-001). Runs as the announcement's tenant.
 */
class NotifyAnnouncementPublished
{
    public function handle(string $announcementId, string $authorId): void
    {
        $announcement = Announcement::query()->with('courseSpace.organization')->find($announcementId);

        if ($announcement === null) {
            return;
        }

        $space = $announcement->courseSpace;

        $students = User::query()
            ->whereIn('id', Enrolment::query()->where('course_space_id', $space->id)->select('student_id'))
            ->whereKeyNot($authorId)
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        $data = [
            'course_space_id' => $space->id,
            'announcement_id' => $announcement->id,
            'course_space_title' => $space->title,
            'announcement_title' => $announcement->title,
        ];

        DB::transaction(function () use ($students, $data): void {
            foreach ($students as $student) {
                Notification::query()->create([
                    'user_id' => $student->id,
                    'kind' => NotificationKind::AnnouncementPublished,
                    'data' => $data,
                ]);
            }
        });

        if (! NotificationKind::AnnouncementPublished->sendsEmail()) {
            return;
        }

        $url = OrganizationUrl::for($space->organization)->route('courses.show', $space);

        foreach ($students as $student) {
            if (! NotificationPreference::emailEnabledFor($student)) {
                continue;
            }

            $this->email($student, $announcement, $space, $url);
        }
    }

    private function email(User $student, Announcement $announcement, CourseSpace $space, string $url): void
    {
        try {
            // An on-demand recipient keeps the user model out of the queued
            // job; the locale is fixed when the email is queued.
            Notifier::route('mail', $student->email)->notify(
                (new AnnouncementPublishedEmail(
                    $student->id,
                    $announcement->id,
                    $space->title,
                    $announcement->title,
                    $url,
                ))->locale($student->preferredLocale()),
            );
        } catch (Throwable $exception) {
            // ARCH-006: ids only, never emails or names.
            Log::warning('notifications.email_not_queued', [
                'kind' => NotificationKind::AnnouncementPublished->value,
                'user_id' => $student->id,
                'announcement_id' => $announcement->id,
                'error' => $exception::class,
            ]);
        }
    }
}

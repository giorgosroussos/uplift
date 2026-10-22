<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\OrganizationUrl;
use App\Modules\Notifications\Enums\NotificationKind;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Notifications\ActivityPublishedEmail;
use App\Modules\Notifications\Support\NotificationMailer;
use Illuminate\Support\Facades\DB;

/**
 * DEC-001: one in-app activity_published notification per student enrolled
 * in the activity's course space when it is published (the publishing
 * teacher excluded), with the space and activity titles as they are now
 * (ids and title snapshots only, DEC-002). Then a queued email to each of
 * them who has not turned notification emails off.
 *
 * The in-app entries are written first and synchronously, so they exist
 * even when queueing or sending the email fails; a failing email never
 * blocks the publish (INT-001). Runs as the activity's tenant.
 */
class NotifyActivityPublished
{
    public function __construct(private readonly NotificationMailer $mailer) {}

    public function handle(string $activityId, string $actorId): void
    {
        $activity = Activity::query()->with('courseSpace.organization')->find($activityId);

        if ($activity === null || ! $activity->isPublished()) {
            return;
        }

        $space = $activity->courseSpace;

        $students = User::query()
            ->whereIn('id', Enrolment::query()->where('course_space_id', $space->id)->select('student_id'))
            ->whereKeyNot($actorId)
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        $data = [
            'course_space_id' => $space->id,
            'activity_id' => $activity->id,
            'course_space_title' => $space->title,
            'activity_title' => $activity->title,
        ];

        DB::transaction(function () use ($students, $data): void {
            foreach ($students as $student) {
                Notification::query()->create([
                    'user_id' => $student->id,
                    'kind' => NotificationKind::ActivityPublished,
                    'data' => $data,
                ]);
            }
        });

        $url = OrganizationUrl::for($space->organization)->route('activities.show', $activity);

        foreach ($students as $student) {
            $this->mailer->queue($student, new ActivityPublishedEmail(
                $student->id,
                $activity->id,
                $space->title,
                $activity->title,
                $url,
            ));
        }
    }
}

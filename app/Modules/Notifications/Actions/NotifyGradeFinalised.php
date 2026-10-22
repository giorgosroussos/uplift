<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\OrganizationUrl;
use App\Modules\Notifications\Enums\NotificationKind;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Notifications\GradeFinalisedEmail;
use App\Modules\Notifications\Support\NotificationMailer;

/**
 * DEC-001: when a quiz attempt gets its final grade, its student gets one
 * in-app grade_finalised notification with the space and activity titles
 * as they are now, and a queued email unless they turned notification
 * emails off. Neither carries the score (SEC-003): the result page shows it
 * under the quiz's visibility rules.
 *
 * The in-app entry is written first and synchronously, so it exists even
 * when queueing or sending the email fails; a failing email never blocks
 * the submission or the grading (INT-001). Runs as the attempt's tenant.
 */
class NotifyGradeFinalised
{
    public function __construct(private readonly NotificationMailer $mailer) {}

    public function handle(string $attemptId): void
    {
        $attempt = QuizAttempt::query()->find($attemptId);

        // Individual attempts only; group attempts come with PHASE-4.
        if ($attempt === null || $attempt->student_id === null) {
            return;
        }

        $student = User::query()->with('organization')->find($attempt->student_id);

        // A deleted activity keeps its attempts and their results (DATA-001).
        $activity = Activity::query()->withTrashed()->with('courseSpace')->find($attempt->quiz_id);

        if ($student === null || $activity === null) {
            return;
        }

        $space = $activity->courseSpace;

        Notification::query()->create([
            'user_id' => $student->id,
            'kind' => NotificationKind::GradeFinalised,
            'data' => [
                'course_space_id' => $space->id,
                'activity_id' => $activity->id,
                'attempt_id' => $attempt->id,
                'course_space_title' => $space->title,
                'activity_title' => $activity->title,
            ],
        ]);

        $this->mailer->queue($student, new GradeFinalisedEmail(
            $student->id,
            $attempt->id,
            $space->title,
            $activity->title,
            OrganizationUrl::for($student->organization)->route('assessment.results.show', $attempt),
        ));
    }
}

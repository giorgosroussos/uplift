<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\OrganizationUrl;
use App\Modules\Notifications\Enums\NotificationKind;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Notifications\GradeChangedEmail;
use App\Modules\Notifications\Notifications\GradeFinalisedEmail;
use App\Modules\Notifications\Support\NotificationMailer;
use InvalidArgumentException;

/**
 * DEC-001 / DEC-003: tells a student about the grade of their quiz attempt
 * when Assessment released it (grade_finalised) or a regrade changed a
 * released result (grade_changed): one in-app entry with the space and
 * activity titles as they are now, and, together with it, a queued email
 * unless they turned notification emails off. Neither carries the score
 * (SEC-003): the result page shows it.
 *
 * The in-app entry is written first and synchronously, so it exists even
 * when queueing or sending the email fails; a failing email never blocks
 * the submission or the grading (INT-001). Runs as the attempt's tenant.
 */
class NotifyGrade
{
    public function __construct(private readonly NotificationMailer $mailer) {}

    public function handle(string $attemptId, NotificationKind $kind): void
    {
        if ($kind !== NotificationKind::GradeFinalised && $kind !== NotificationKind::GradeChanged) {
            throw new InvalidArgumentException('Not a grade notification kind.');
        }

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
            'kind' => $kind,
            'data' => [
                'course_space_id' => $space->id,
                'activity_id' => $activity->id,
                'attempt_id' => $attempt->id,
                'course_space_title' => $space->title,
                'activity_title' => $activity->title,
            ],
        ]);

        $url = OrganizationUrl::for($student->organization)->route('assessment.results.show', $attempt);

        $this->mailer->queue($student, $kind === NotificationKind::GradeChanged
            ? new GradeChangedEmail($student->id, $attempt->id, $space->title, $activity->title, $url)
            : new GradeFinalisedEmail($student->id, $attempt->id, $space->title, $activity->title, $url));
    }
}

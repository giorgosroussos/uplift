<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationKind;

/**
 * DEC-001 / DEC-003: email to a student whose quiz result was released,
 * with the course space name, the activity title and a link to the result;
 * never the score (SEC-003). Queued with the INT-001 retry policy
 * (NotificationEmail).
 */
class GradeFinalisedEmail extends NotificationEmail
{
    use GradeEmail;

    public function kind(): NotificationKind
    {
        return NotificationKind::GradeFinalised;
    }
}

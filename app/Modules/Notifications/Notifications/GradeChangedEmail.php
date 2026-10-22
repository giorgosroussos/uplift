<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationKind;

/**
 * DEC-003: email to a student whose released quiz result changed after a
 * regrade, with the course space name, the activity title and a link to
 * the result; never the score (SEC-003). Queued with the INT-001 retry
 * policy (NotificationEmail).
 */
class GradeChangedEmail extends NotificationEmail
{
    use GradeEmail;

    public function kind(): NotificationKind
    {
        return NotificationKind::GradeChanged;
    }
}

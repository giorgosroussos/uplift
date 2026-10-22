<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

/**
 * DEC-001: email to a student whose quiz attempt got its final grade, with
 * the course space name, the activity title and a link to the result;
 * never the score (SEC-003), which the result page shows under the quiz's
 * visibility rules. Queued with the INT-001 retry policy (NotificationEmail).
 */
class GradeFinalisedEmail extends NotificationEmail
{
    public function __construct(
        public readonly string $userId,
        public readonly string $attemptId,
        public readonly string $courseSpaceTitle,
        public readonly string $activityTitle,
        public readonly string $url,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::GradeFinalised;
    }

    /**
     * @return array<string, string>
     */
    public function logContext(): array
    {
        return ['user_id' => $this->userId, 'attempt_id' => $this->attemptId];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.grade_finalised.subject', ['title' => $this->activityTitle]))
            ->greeting(__('notifications.greeting'))
            ->line(__('notifications.grade_finalised.intro', ['title' => $this->activityTitle, 'space' => $this->courseSpaceTitle]))
            ->action(__('notifications.grade_finalised.action'), $this->url)
            ->line(__('notifications.outro'))
            ->salutation(__('notifications.salutation', ['app' => Config::get('app.name')]));
    }
}

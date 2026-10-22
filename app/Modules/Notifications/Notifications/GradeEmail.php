<?php

namespace App\Modules\Notifications\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

/**
 * Content of the grade emails (DEC-001 / DEC-003): the course space name,
 * the activity title and a link to the result, never the score (SEC-003).
 * The copy comes from notifications.<kind>.*. A trait rather than a base
 * class, so each email declares its own readonly properties and can be
 * unserialised by the queue.
 */
trait GradeEmail
{
    public function __construct(
        public readonly string $userId,
        public readonly string $attemptId,
        public readonly string $courseSpaceTitle,
        public readonly string $activityTitle,
        public readonly string $url,
    ) {}

    /**
     * @return array<string, string>
     */
    public function logContext(): array
    {
        return ['user_id' => $this->userId, 'attempt_id' => $this->attemptId];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $keys = 'notifications.'.$this->kind()->value;

        return (new MailMessage)
            ->subject(__($keys.'.subject', ['title' => $this->activityTitle]))
            ->greeting(__('notifications.greeting'))
            ->line(__($keys.'.intro', ['title' => $this->activityTitle, 'space' => $this->courseSpaceTitle]))
            ->action(__($keys.'.action'), $this->url)
            ->line(__('notifications.outro'))
            ->salutation(__('notifications.salutation', ['app' => Config::get('app.name')]));
    }
}

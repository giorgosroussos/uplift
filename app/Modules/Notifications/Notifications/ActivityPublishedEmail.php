<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

/**
 * DEC-001: email about a new activity in an enrolled course space, with the
 * course space name, the activity title and a link to the activity; never
 * its description. Queued with the INT-001 retry policy (NotificationEmail).
 */
class ActivityPublishedEmail extends NotificationEmail
{
    public function __construct(
        public readonly string $userId,
        public readonly string $activityId,
        public readonly string $courseSpaceTitle,
        public readonly string $activityTitle,
        public readonly string $url,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::ActivityPublished;
    }

    /**
     * @return array<string, string>
     */
    public function logContext(): array
    {
        return ['user_id' => $this->userId, 'activity_id' => $this->activityId];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.activity_published.subject', ['space' => $this->courseSpaceTitle]))
            ->greeting(__('notifications.greeting'))
            ->line(__('notifications.activity_published.intro', ['space' => $this->courseSpaceTitle]))
            ->line(__('notifications.activity_published.title', ['title' => $this->activityTitle]))
            ->action(__('notifications.activity_published.action'), $this->url)
            ->line(__('notifications.outro'))
            ->salutation(__('notifications.salutation', ['app' => Config::get('app.name')]));
    }
}

<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

/**
 * DEC-001 / DEC-002: email about a new announcement, with the course space
 * name, the announcement title and a link to the course space; never the
 * body. Queued with the INT-001 retry policy (NotificationEmail).
 */
class AnnouncementPublishedEmail extends NotificationEmail
{
    public function __construct(
        public readonly string $userId,
        public readonly string $announcementId,
        public readonly string $courseSpaceTitle,
        public readonly string $announcementTitle,
        public readonly string $url,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::AnnouncementPublished;
    }

    /**
     * @return array<string, string>
     */
    public function logContext(): array
    {
        return ['user_id' => $this->userId, 'announcement_id' => $this->announcementId];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.announcement_published.subject', ['space' => $this->courseSpaceTitle]))
            ->greeting(__('notifications.greeting'))
            ->line(__('notifications.announcement_published.intro', ['space' => $this->courseSpaceTitle]))
            ->line(__('notifications.announcement_published.title', ['title' => $this->announcementTitle]))
            ->action(__('notifications.announcement_published.action'), $this->url)
            ->line(__('notifications.announcement_published.outro'))
            ->salutation(__('notifications.salutation', ['app' => Config::get('app.name')]));
    }
}

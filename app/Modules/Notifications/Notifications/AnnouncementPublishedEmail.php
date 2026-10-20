<?php

namespace App\Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * DEC-001 / DEC-002: email about a new announcement, with the course space
 * name, the announcement title and a link to the course space; never the
 * body. Sent in the recipient's locale (set by the sender), from a queued
 * job with the retry policy of INT-001: timeout 10s, 5 retries with backoff
 * 1m, 5m, 15m, 1h, 6h.
 */
class AnnouncementPublishedEmail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * One attempt plus 5 retries (INT-001).
     */
    public int $tries = 6;

    public int $timeout = 10;

    public function __construct(
        public readonly string $userId,
        public readonly string $announcementId,
        public readonly string $courseSpaceTitle,
        public readonly string $announcementTitle,
        public readonly string $url,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 21600];
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
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

    /**
     * After the last retry (ARCH-006: ids only, never emails or names). The
     * in-app notification exists regardless (DEC-002).
     */
    public function failed(Throwable $exception): void
    {
        Log::warning('notifications.email_failed', [
            'kind' => 'announcement_published',
            'user_id' => $this->userId,
            'announcement_id' => $this->announcementId,
            'error' => $exception::class,
        ]);
    }
}

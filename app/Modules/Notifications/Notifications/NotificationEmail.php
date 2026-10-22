<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationKind;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A DEC-001 notification email. Sent in the recipient's locale (set by the
 * sender) from a queued job with the retry policy of INT-001: timeout 10s,
 * 5 retries with backoff 1m, 5m, 15m, 1h, 6h. A failing email never
 * touches the in-app notification, which exists regardless (DEC-002).
 */
abstract class NotificationEmail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * One attempt plus 5 retries (INT-001).
     */
    public int $tries = 6;

    public int $timeout = 10;

    abstract public function kind(): NotificationKind;

    /**
     * Ids that identify the email in logs (ARCH-006: ids only, never emails,
     * names or scores).
     *
     * @return array<string, string>
     */
    abstract public function logContext(): array;

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

    /**
     * After the last retry. The in-app notification exists regardless.
     */
    public function failed(Throwable $exception): void
    {
        Log::warning('notifications.email_failed', [
            'kind' => $this->kind()->value,
            ...$this->logContext(),
            'error' => $exception::class,
        ]);
    }
}

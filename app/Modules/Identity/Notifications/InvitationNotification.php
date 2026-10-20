<?php

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

/**
 * Invitation email with the single-use activation link (UC-032, FLOW-003),
 * sent from a queued job on the queued mail channel with the retry policy
 * of INT-001: timeout 10s, 5 retries with backoff 1m, 5m, 15m, 1h, 6h.
 */
class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * One attempt plus 5 retries (INT-001).
     */
    public int $tries = 6;

    public int $timeout = 10;

    public function __construct(
        public readonly string $invitationId,
        public readonly string $url,
        public readonly string $organizationName,
        public readonly string $role,
        public readonly int $validDays,
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
            ->subject(__('notifications.invitation.subject', ['organization' => $this->organizationName]))
            ->greeting(__('notifications.greeting'))
            ->line(__('notifications.invitation.intro', [
                'organization' => $this->organizationName,
                'role' => __('users.roles.'.$this->role),
            ]))
            ->action(__('notifications.invitation.action'), $this->url)
            ->line(__('notifications.invitation.expires', ['count' => $this->validDays]))
            ->line(__('notifications.invitation.outro'))
            ->salutation(__('notifications.salutation', ['app' => Config::get('app.name')]));
    }
}

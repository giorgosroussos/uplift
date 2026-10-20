<?php

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

/**
 * Email verification link (UC-004), sent from a queued job (EXT-002). The
 * signed link is built when the mail is requested, on the organisation's
 * subdomain.
 */
class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $url) {}

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
            ->subject(__('notifications.verify_email.subject'))
            ->greeting(__('notifications.greeting'))
            ->line(__('notifications.verify_email.intro'))
            ->action(__('notifications.verify_email.action'), $this->url)
            ->line(__('notifications.verify_email.outro'))
            ->salutation(__('notifications.salutation', ['app' => Config::get('app.name')]));
    }
}

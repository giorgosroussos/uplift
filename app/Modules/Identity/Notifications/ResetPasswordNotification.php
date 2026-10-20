<?php

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

/**
 * Password reset link (UC-004), sent from a queued job (EXT-002). The link is
 * built when the reset is requested, on the organisation's subdomain.
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
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
        $expire = (int) Config::get('auth.passwords.'.Config::get('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject(__('notifications.reset_password.subject'))
            ->greeting(__('notifications.greeting'))
            ->line(__('notifications.reset_password.intro'))
            ->action(__('notifications.reset_password.action'), $this->url)
            ->line(__('notifications.reset_password.expires', ['count' => $expire]))
            ->line(__('notifications.reset_password.outro'))
            ->salutation(__('notifications.salutation', ['app' => Config::get('app.name')]));
    }
}

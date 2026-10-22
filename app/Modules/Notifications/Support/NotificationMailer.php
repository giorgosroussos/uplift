<?php

namespace App\Modules\Notifications\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Notifications\NotificationEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notifier;
use Throwable;

/**
 * Queues a DEC-001 notification email to a user (INT-001): only when the
 * kind sends email and the user has not turned notification emails off
 * (DEC-002), in the user's locale, else the organisation's default_locale.
 * A queue that refuses the job is logged with ids only and never reaches
 * the caller, so no user flow is blocked; the in-app notification has been
 * written before.
 */
final class NotificationMailer
{
    public function queue(User $recipient, NotificationEmail $email): void
    {
        if (! $email->kind()->sendsEmail() || ! NotificationPreference::emailEnabledFor($recipient)) {
            return;
        }

        try {
            // An on-demand recipient keeps the user model out of the queued
            // job; the locale is fixed when the email is queued.
            Notifier::route('mail', $recipient->email)
                ->notify($email->locale($recipient->preferredLocale()));
        } catch (Throwable $exception) {
            // ARCH-006: ids only, never emails or names.
            Log::warning('notifications.email_not_queued', [
                'kind' => $email->kind()->value,
                ...$email->logContext(),
                'error' => $exception::class,
            ]);
        }
    }
}

<?php

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Notifications\InvitationNotification;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\Notification;

/**
 * Queues the invitation email with the link for the given token, in the
 * organisation's default locale (UC-032, DATA-015).
 */
final class InvitationMail
{
    public static function queue(Invitation $invitation, Organization $organization, #[\SensitiveParameter] string $token): void
    {
        $url = OrganizationUrl::for($organization)->route('invitation.show', [
            'invitation' => $invitation->getKey(),
            'token' => $token,
        ]);

        Notification::route('mail', $invitation->email)
            ->notify((new InvitationNotification(
                (string) $invitation->getKey(),
                $url,
                $organization->name,
                $invitation->role->value,
                Invitation::VALID_DAYS,
                $token,
            ))->locale($organization->default_locale->value));
    }
}

<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

/**
 * DEC-001 / SCOPE-025: email to a student invited to a group, with the
 * course space name, the group name and a link to the group, where they
 * accept or decline; never who invited them. Queued with the INT-001 retry
 * policy (NotificationEmail).
 */
class GroupInvitationEmail extends NotificationEmail
{
    public function __construct(
        public readonly string $userId,
        public readonly string $groupMembershipId,
        public readonly string $courseSpaceTitle,
        public readonly string $groupName,
        public readonly string $url,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::GroupInvitation;
    }

    /**
     * @return array<string, string>
     */
    public function logContext(): array
    {
        return ['user_id' => $this->userId, 'group_membership_id' => $this->groupMembershipId];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = ['group' => $this->groupName, 'space' => $this->courseSpaceTitle];

        return (new MailMessage)
            ->subject(__('notifications.group_invitation.subject', $replace))
            ->greeting(__('notifications.greeting'))
            ->line(__('notifications.group_invitation.intro', $replace))
            ->action(__('notifications.group_invitation.action'), $this->url)
            ->line(__('notifications.outro'))
            ->salutation(__('notifications.salutation', ['app' => Config::get('app.name')]));
    }
}

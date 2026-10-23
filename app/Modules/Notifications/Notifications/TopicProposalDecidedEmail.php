<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Enums\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

/**
 * DEC-001: email to the proposing student, or to each member of the
 * proposing group, that their topic proposal was approved or rejected, with
 * the course space name, the activity title and a link to the proposals
 * page; never the proposal's description or the teacher's comment, which
 * the page shows. Queued with the INT-001 retry policy (NotificationEmail).
 */
class TopicProposalDecidedEmail extends NotificationEmail
{
    /**
     * @param  string  $status  approved or rejected
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $topicProposalId,
        public readonly string $status,
        public readonly string $courseSpaceTitle,
        public readonly string $activityTitle,
        public readonly string $url,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::TopicProposalDecided;
    }

    /**
     * @return array<string, string>
     */
    public function logContext(): array
    {
        return ['user_id' => $this->userId, 'topic_proposal_id' => $this->topicProposalId];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $keys = 'notifications.topic_proposal_decided.'.$this->status;
        $replace = ['title' => $this->activityTitle, 'space' => $this->courseSpaceTitle];

        return (new MailMessage)
            ->subject(__($keys.'.subject', $replace))
            ->greeting(__('notifications.greeting'))
            ->line(__($keys.'.intro', $replace))
            ->action(__('notifications.topic_proposal_decided.action'), $this->url)
            ->line(__('notifications.outro'))
            ->salutation(__('notifications.salutation', ['app' => Config::get('app.name')]));
    }
}

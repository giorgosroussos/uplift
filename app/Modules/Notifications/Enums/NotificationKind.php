<?php

namespace App\Modules\Notifications\Enums;

/**
 * Kinds of in-app notifications (DEC-002). Later phases add their kinds
 * here.
 */
enum NotificationKind: string
{
    case AnnouncementPublished = 'announcement_published';
    case ActivityPublished = 'activity_published';
    case GradeFinalised = 'grade_finalised';
    case GradeChanged = 'grade_changed';
    case TopicProposalDecided = 'topic_proposal_decided';
    case GroupInvitation = 'group_invitation';

    /**
     * Whether the kind is one of the DEC-001 notification emails (new
     * announcement, new activity in an enrolled course space, new or final
     * grade, topic approved or rejected, group invitation). Every other
     * kind is in-app only (DEC-001).
     */
    public function sendsEmail(): bool
    {
        return match ($this) {
            self::AnnouncementPublished,
            self::ActivityPublished,
            self::GradeFinalised,
            self::GradeChanged,
            self::TopicProposalDecided,
            self::GroupInvitation => true,
        };
    }
}

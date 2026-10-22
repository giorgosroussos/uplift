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

    /**
     * Whether the kind is one of the DEC-001 notification emails (new
     * announcement, new activity in an enrolled course space, new or final
     * grade, ...). Every other kind is in-app only (DEC-001).
     */
    public function sendsEmail(): bool
    {
        return match ($this) {
            self::AnnouncementPublished, self::ActivityPublished, self::GradeFinalised, self::GradeChanged => true,
        };
    }
}

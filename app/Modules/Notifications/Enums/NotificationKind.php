<?php

namespace App\Modules\Notifications\Enums;

/**
 * Kinds of in-app notifications (DEC-002). PHASE-2 has only
 * announcement_published; later phases add their kinds here.
 */
enum NotificationKind: string
{
    case AnnouncementPublished = 'announcement_published';

    /**
     * Whether the kind is one of the DEC-001 notification emails. Every other
     * kind is in-app only (DEC-001).
     */
    public function sendsEmail(): bool
    {
        return match ($this) {
            self::AnnouncementPublished => true,
        };
    }
}

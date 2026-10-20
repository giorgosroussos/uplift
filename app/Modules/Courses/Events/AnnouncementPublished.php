<?php

namespace App\Modules\Courses\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A teacher published an announcement in a course space (UC-008), for the
 * Notifications module to notify the enrolled students (DEC-001).
 * Dispatched after the announcement has committed; carries ids only.
 */
final class AnnouncementPublished
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $courseSpaceId,
        public readonly string $announcementId,
        public readonly string $authorId,
    ) {}
}

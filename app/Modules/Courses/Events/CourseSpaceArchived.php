<?php

namespace App\Modules\Courses\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The owning teacher archived a course space (UC-021, DATA-012). Archiving
 * deleted all its enrolments; removedEnrolments counts them. For the Audit
 * module (course_space.archived). Dispatched after the archive has
 * committed; carries ids only.
 */
final class CourseSpaceArchived implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $courseSpaceId,
        public readonly string $actorId,
        public readonly int $removedEnrolments,
    ) {}
}

<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Actions\AuditRecord;
use App\Modules\Audit\Enums\ActorType;
use App\Modules\Courses\Events\CourseSpaceArchived;

/**
 * course_space.archived: the owning teacher archived a course space
 * (UC-021, DATA-012, SEC-010). One entry; changes hold the number of
 * enrolments the archive removed.
 */
final class AuditCourseSpaceArchived extends AuditListener
{
    protected function toRecord(object $event): AuditRecord
    {
        assert($event instanceof CourseSpaceArchived);

        return new AuditRecord(
            action: 'course_space.archived',
            actorType: ActorType::User,
            actorId: $event->actorId,
            subjectType: 'course_space',
            subjectId: $event->courseSpaceId,
            organizationId: $event->organizationId,
            changes: ['removed_enrolments' => $event->removedEnrolments],
        );
    }
}

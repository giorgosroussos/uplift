<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Events\CourseSpaceArchived;
use App\Modules\Courses\Exceptions\InvalidCourseSpaceTransition;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * UC-021: archives a course space when the course ends, or makes it active
 * again, following the state model.
 *
 * DATA-012: archiving deletes every enrolment of the space in the same
 * transaction and dispatches CourseSpaceArchived after commit; restoring
 * gives an active space without students, who enrol again.
 */
class ChangeCourseSpaceStatus
{
    /**
     * @throws InvalidCourseSpaceTransition when the state model does not allow it
     */
    public function handle(User $actor, CourseSpace $space, CourseSpaceStatus $to): CourseSpace
    {
        DB::transaction(function () use ($actor, $space, $to): void {
            // Lock the row so concurrent changes of status and enrolments
            // (EnrolStudent) wait, then work on the committed status.
            CourseSpace::query()->whereKey($space->getKey())->lockForUpdate()->firstOrFail();
            $space->refresh();

            $space->transitionTo($to);

            if ($to !== CourseSpaceStatus::Archived) {
                return;
            }

            $removed = $space->enrolments()->delete();

            CourseSpaceArchived::dispatch(
                $space->organization_id,
                $space->id,
                (string) $actor->getKey(),
                $removed,
            );
        });

        return $space;
    }
}

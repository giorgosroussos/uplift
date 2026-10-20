<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Exceptions\InvalidCourseSpaceTransition;
use App\Modules\Courses\Models\CourseSpace;

/**
 * UC-021: archives a course space when the course ends, or makes it active
 * again, following the state model.
 */
class ChangeCourseSpaceStatus
{
    /**
     * @throws InvalidCourseSpaceTransition when the state model does not allow it
     */
    public function handle(CourseSpace $space, CourseSpaceStatus $to): CourseSpace
    {
        $space->transitionTo($to);

        return $space;
    }
}

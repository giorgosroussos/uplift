<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Models\Enrolment;

/**
 * UC-021: the owning teacher removes a student from a course space.
 */
class RemoveStudentFromCourseSpace
{
    public function handle(Enrolment $enrolment): void
    {
        $enrolment->delete();
    }
}

<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;

/**
 * UC-021: a teacher creates a course space and becomes its owner. The space
 * belongs to the teacher's organisation and starts active.
 */
class CreateCourseSpace
{
    /**
     * @param  array{title: string, description?: string|null, enrolment_policy: string, enrolment_key?: string|null}  $input
     */
    public function handle(User $teacher, array $input): CourseSpace
    {
        $space = new CourseSpace($input);
        $space->organization_id = $teacher->organization_id;
        $space->teacher_id = $teacher->getKey();
        $space->save();

        return $space;
    }
}

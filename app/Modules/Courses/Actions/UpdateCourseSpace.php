<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Models\CourseSpace;

/**
 * UC-021: the owning teacher edits title, description and enrolment policy.
 * Owner, organisation and status never change here.
 */
class UpdateCourseSpace
{
    /**
     * @param  array{title: string, description?: string|null, enrolment_policy: string, enrolment_key?: string|null}  $input
     */
    public function handle(CourseSpace $space, array $input): CourseSpace
    {
        $space->fill($input)->save();

        return $space;
    }
}

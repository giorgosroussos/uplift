<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\Basics;
use App\Modules\Courses\Models\CourseSpace;

/**
 * UC-022 step 1: the first autosave of the wizard creates the activity as
 * a draft in the teacher's course space, with its type and basics.
 */
class CreateActivity
{
    /**
     * @param  array<string, mixed>  $basics  validated basics
     */
    public function handle(CourseSpace $space, ActivityKind $kind, array $basics): Activity
    {
        $activity = new Activity(Basics::normalize($basics));
        $activity->organization_id = $space->organization_id;
        $activity->course_space_id = $space->id;
        $activity->type = $kind;
        $activity->is_group = false;
        $activity->save();

        return $activity;
    }
}

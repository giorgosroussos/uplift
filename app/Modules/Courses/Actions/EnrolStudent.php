<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;

/**
 * Writes the enrolment of a student in a course space exactly once: a
 * concurrent or repeated enrolment returns the existing row (the unique
 * constraint on the pair decides).
 */
class EnrolStudent
{
    public function handle(CourseSpace $space, User $student): Enrolment
    {
        return Enrolment::query()->createOrFirst(
            ['course_space_id' => $space->getKey(), 'student_id' => $student->getKey()],
            ['enrolled_at' => now()],
        );
    }
}

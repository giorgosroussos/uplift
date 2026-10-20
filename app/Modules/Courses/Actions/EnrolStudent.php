<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Writes the enrolment of a student in an active course space exactly once:
 * a concurrent or repeated enrolment returns the existing row (the unique
 * constraint on the pair decides).
 *
 * The space row is share-locked, so an enrolment never slips in while the
 * space is being archived (DATA-012); null when the space is not active.
 */
class EnrolStudent
{
    public function handle(CourseSpace $space, User $student): ?Enrolment
    {
        return DB::transaction(function () use ($space, $student): ?Enrolment {
            $current = CourseSpace::query()->whereKey($space->getKey())->sharedLock()->first();

            if ($current === null || ! $current->isActive()) {
                return null;
            }

            return Enrolment::query()->createOrFirst(
                ['course_space_id' => $space->getKey(), 'student_id' => $student->getKey()],
                ['enrolled_at' => now()],
            );
        });
    }
}

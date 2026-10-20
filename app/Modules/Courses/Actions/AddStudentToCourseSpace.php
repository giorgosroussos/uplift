<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * UC-021: the owning teacher adds a student of the organisation to an active
 * course space, without an enrolment key. Adding an enrolled student again
 * changes nothing.
 */
class AddStudentToCourseSpace
{
    public function __construct(private readonly EnrolStudent $enrol) {}

    /**
     * @throws ValidationException when the user is not an active student of the organisation or the space is archived
     */
    public function handle(CourseSpace $space, string $studentId): Enrolment
    {
        // The tenant scope limits the lookup to the current organisation.
        $student = User::query()
            ->whereKey($studentId)
            ->where('role', Role::Student)
            ->where('is_active', true)
            ->first();

        if ($student === null || $student->organization_id !== $space->organization_id) {
            throw ValidationException::withMessages([
                'student_id' => __('courses.errors.not_a_student'),
            ]);
        }

        if (! $space->isActive()) {
            throw ValidationException::withMessages([
                'student_id' => __('courses.errors.archived'),
            ]);
        }

        return $this->enrol->handle($space, $student) ?? throw ValidationException::withMessages([
            'student_id' => __('courses.errors.archived'),
        ]);
    }
}

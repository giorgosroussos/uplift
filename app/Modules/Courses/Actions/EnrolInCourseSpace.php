<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Enums\EnrolmentPolicy;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * UC-011: a student enrols in a course space, directly when its policy is
 * open, with the enrolment key when its policy is key.
 *
 * Enrolling again returns the existing enrolment (never a duplicate); an
 * archived space refuses new enrolments; a wrong key enrols nobody.
 */
class EnrolInCourseSpace
{
    public function __construct(private readonly EnrolStudent $enrol) {}

    /**
     * @throws ValidationException when the key is wrong or the space is archived
     */
    public function handle(User $student, CourseSpace $space, #[\SensitiveParameter] ?string $key): Enrolment
    {
        $existing = $space->enrolments()->where('student_id', $student->getKey())->first();

        if ($existing !== null) {
            return $existing;
        }

        if (! $space->isActive()) {
            throw ValidationException::withMessages([
                'enrolment' => __('courses.errors.archived'),
            ]);
        }

        if ($space->enrolment_policy === EnrolmentPolicy::Key && ! $space->acceptsKey($key)) {
            throw ValidationException::withMessages([
                'enrolment_key' => __('courses.errors.wrong_key'),
            ]);
        }

        return $this->enrol->handle($space, $student) ?? throw ValidationException::withMessages([
            'enrolment' => __('courses.errors.archived'),
        ]);
    }
}

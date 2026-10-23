<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use Illuminate\Support\Facades\DB;

/**
 * UC-021: the owning teacher removes a student from a course space. In the
 * same transaction the student's group memberships of the space end
 * (SCOPE-025, ReleaseStudentFromGroups).
 */
class RemoveStudentFromCourseSpace
{
    public function __construct(private readonly ReleaseStudentFromGroups $release) {}

    public function handle(Enrolment $enrolment): void
    {
        DB::transaction(function () use ($enrolment): void {
            // Lock the space so concurrent group creation, invitations and
            // acceptances (which share-lock it) see the removal.
            CourseSpace::query()->whereKey($enrolment->course_space_id)->lockForUpdate()->firstOrFail();

            $enrolment->delete();

            $this->release->handle($enrolment->course_space_id, $enrolment->student_id);
        });
    }
}

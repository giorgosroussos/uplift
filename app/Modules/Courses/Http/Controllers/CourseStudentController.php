<?php

namespace App\Modules\Courses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Courses\Actions\AddStudentToCourseSpace;
use App\Modules\Courses\Actions\RemoveStudentFromCourseSpace;
use App\Modules\Courses\Http\Requests\AddStudentRequest;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * UC-021: the owning teacher adds students to and removes them from a
 * course space (OWN-003).
 */
class CourseStudentController extends Controller
{
    public function store(AddStudentRequest $request, CourseSpace $courseSpace, AddStudentToCourseSpace $add): RedirectResponse
    {
        Gate::authorize('manageEnrolments', $courseSpace);

        $add->handle($courseSpace, $request->studentId());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('courses.flash.student_added')]);

        return to_route('courses.show', $courseSpace);
    }

    public function destroy(CourseSpace $courseSpace, Enrolment $enrolment, RemoveStudentFromCourseSpace $remove): RedirectResponse
    {
        Gate::authorize('manageEnrolments', $courseSpace);
        Gate::authorize('delete', $enrolment);

        $remove->handle($enrolment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('courses.flash.student_removed')]);

        return to_route('courses.show', $courseSpace);
    }
}

<?php

namespace App\Modules\Courses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Courses\Actions\EnrolInCourseSpace;
use App\Modules\Courses\Http\Requests\EnrolRequest;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * UC-011: a student enrols in a course space (rate-limited, SEC-010).
 */
class EnrolmentController extends Controller
{
    public function store(EnrolRequest $request, CourseSpace $courseSpace, EnrolInCourseSpace $enrol): RedirectResponse
    {
        Gate::authorize('enrol', $courseSpace);

        /** @var User $student */
        $student = $request->user();

        $enrol->handle($student, $courseSpace, $request->enrolmentKey());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('courses.flash.enrolled')]);

        return to_route('courses.show', $courseSpace);
    }
}

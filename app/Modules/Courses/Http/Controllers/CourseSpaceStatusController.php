<?php

namespace App\Modules\Courses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Courses\Actions\ChangeCourseSpaceStatus;
use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Exceptions\InvalidCourseSpaceTransition;
use App\Modules\Courses\Models\CourseSpace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * UC-021: the owning teacher archives a course space when the course ends,
 * or makes it active again. Transitions outside the state model are refused.
 */
class CourseSpaceStatusController extends Controller
{
    public function archive(CourseSpace $courseSpace, ChangeCourseSpaceStatus $change): RedirectResponse
    {
        return $this->transition($courseSpace, CourseSpaceStatus::Archived, $change, 'courses.flash.archived');
    }

    public function restore(CourseSpace $courseSpace, ChangeCourseSpaceStatus $change): RedirectResponse
    {
        return $this->transition($courseSpace, CourseSpaceStatus::Active, $change, 'courses.flash.restored');
    }

    private function transition(CourseSpace $courseSpace, CourseSpaceStatus $to, ChangeCourseSpaceStatus $change, string $message): RedirectResponse
    {
        Gate::authorize('update', $courseSpace);

        try {
            $change->handle($courseSpace, $to);
        } catch (InvalidCourseSpaceTransition) {
            return to_route('courses.show', $courseSpace)
                ->withErrors(['status' => __('courses.errors.invalid_transition')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);

        return to_route('courses.show', $courseSpace);
    }
}

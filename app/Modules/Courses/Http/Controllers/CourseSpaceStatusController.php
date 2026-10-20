<?php

namespace App\Modules\Courses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Courses\Actions\ChangeCourseSpaceStatus;
use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Exceptions\InvalidCourseSpaceTransition;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * UC-021: the owning teacher archives a course space when the course ends,
 * or makes it active again. Transitions outside the state model are refused.
 * Archiving unenrols every student (DATA-012); the page asks to confirm it.
 */
class CourseSpaceStatusController extends Controller
{
    public function archive(Request $request, CourseSpace $courseSpace, ChangeCourseSpaceStatus $change): RedirectResponse
    {
        return $this->transition($request, $courseSpace, CourseSpaceStatus::Archived, $change, 'courses.flash.archived');
    }

    public function restore(Request $request, CourseSpace $courseSpace, ChangeCourseSpaceStatus $change): RedirectResponse
    {
        return $this->transition($request, $courseSpace, CourseSpaceStatus::Active, $change, 'courses.flash.restored');
    }

    private function transition(Request $request, CourseSpace $courseSpace, CourseSpaceStatus $to, ChangeCourseSpaceStatus $change, string $message): RedirectResponse
    {
        Gate::authorize('update', $courseSpace);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $change->handle($actor, $courseSpace, $to);
        } catch (InvalidCourseSpaceTransition) {
            return to_route('courses.show', $courseSpace)
                ->withErrors(['status' => __('courses.errors.invalid_transition')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);

        return to_route('courses.show', $courseSpace);
    }
}

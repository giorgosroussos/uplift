<?php

namespace App\Modules\Courses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Courses\Actions\CreateCourseSpace;
use App\Modules\Courses\Actions\ListCourseSpaces;
use App\Modules\Courses\Actions\ShowCourseSpace;
use App\Modules\Courses\Actions\UpdateCourseSpace;
use App\Modules\Courses\Http\Requests\CourseSpaceRequest;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-021: course spaces of the organisation; route model binding only finds
 * spaces of the current tenant, the policy applies OWN-004.
 */
class CourseSpaceController extends Controller
{
    public function index(Request $request, ListCourseSpaces $list): Response
    {
        $user = $this->user($request);
        Gate::authorize('viewAny', CourseSpace::class);

        return Inertia::render('Courses/Index', [
            'spaces' => $list->handle($user),
            'canCreate' => $user->can('create', CourseSpace::class),
            'canBrowseCatalogue' => $user->can('browseCatalogue', CourseSpace::class),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', CourseSpace::class);

        return Inertia::render('Courses/Create');
    }

    public function store(CourseSpaceRequest $request, CreateCourseSpace $create): RedirectResponse
    {
        Gate::authorize('create', CourseSpace::class);

        $space = $create->handle($this->user($request), $request->courseSpace());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('courses.flash.created')]);

        return to_route('courses.show', $space);
    }

    public function show(Request $request, CourseSpace $courseSpace, ShowCourseSpace $show): Response
    {
        Gate::authorize('view', $courseSpace);

        return Inertia::render('Courses/Show', $show->handle($this->user($request), $courseSpace));
    }

    public function edit(CourseSpace $courseSpace): Response
    {
        Gate::authorize('update', $courseSpace);

        return Inertia::render('Courses/Edit', [
            'space' => [
                'id' => $courseSpace->id,
                'title' => $courseSpace->title,
                'description' => $courseSpace->description,
                'enrolment_policy' => $courseSpace->enrolment_policy->value,
                'enrolment_key' => $courseSpace->enrolment_key,
            ],
        ]);
    }

    public function update(CourseSpaceRequest $request, CourseSpace $courseSpace, UpdateCourseSpace $update): RedirectResponse
    {
        Gate::authorize('update', $courseSpace);

        $update->handle($courseSpace, $request->courseSpace());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('courses.flash.updated')]);

        return to_route('courses.show', $courseSpace);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

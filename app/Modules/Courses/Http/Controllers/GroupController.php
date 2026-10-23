<?php

namespace App\Modules\Courses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Courses\Actions\CreateGroup;
use App\Modules\Courses\Actions\DissolveGroup;
use App\Modules\Courses\Actions\ListGroups;
use App\Modules\Courses\Actions\RenameGroup;
use App\Modules\Courses\Actions\ShowGroup;
use App\Modules\Courses\Http\Requests\GroupRequest;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-019: the groups of a course space. Students create, rename and dissolve
 * groups; the owning teacher and org admins only read them (OWN-005).
 * Route model binding finds only groups of the current tenant that are not
 * deleted.
 */
class GroupController extends Controller
{
    public function index(Request $request, CourseSpace $courseSpace, ListGroups $list): Response
    {
        Gate::authorize('viewGroups', [Group::class, $courseSpace]);
        $user = $this->user($request);

        return Inertia::render('Courses/Groups/Index', [
            'space' => $this->space($courseSpace),
            'groups' => $list->handle($user, $courseSpace),
            'canCreate' => $user->can('createGroup', [Group::class, $courseSpace]),
        ]);
    }

    public function store(GroupRequest $request, CourseSpace $courseSpace, CreateGroup $create): RedirectResponse
    {
        Gate::authorize('createGroup', [Group::class, $courseSpace]);

        $group = $create->handle($this->user($request), $courseSpace, $request->name());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('groups.flash.created')]);

        return to_route('courses.groups.show', [$courseSpace, $group]);
    }

    public function show(Request $request, CourseSpace $courseSpace, Group $group, ShowGroup $show): Response
    {
        Gate::authorize('view', $group);

        return Inertia::render('Courses/Groups/Show', [
            'space' => $this->space($courseSpace),
            ...$show->handle($this->user($request), $group),
        ]);
    }

    public function update(GroupRequest $request, CourseSpace $courseSpace, Group $group, RenameGroup $rename): RedirectResponse
    {
        Gate::authorize('update', $group);

        $rename->handle($group, $request->name());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('groups.flash.renamed')]);

        return to_route('courses.groups.show', [$courseSpace, $group]);
    }

    public function destroy(CourseSpace $courseSpace, Group $group, DissolveGroup $dissolve): RedirectResponse
    {
        Gate::authorize('delete', $group);

        $dissolve->handle($group);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('groups.flash.dissolved')]);

        return to_route('courses.groups.index', $courseSpace);
    }

    /**
     * @return array{id: string, title: string, status: string}
     */
    private function space(CourseSpace $space): array
    {
        return ['id' => $space->id, 'title' => $space->title, 'status' => $space->status->value];
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

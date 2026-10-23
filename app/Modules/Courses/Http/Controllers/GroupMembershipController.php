<?php

namespace App\Modules\Courses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Courses\Actions\LeaveGroup;
use App\Modules\Courses\Actions\TransferGroupOwnership;
use App\Modules\Courses\Http\Requests\GroupStudentRequest;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * UC-019: a member leaves the group; the owner hands ownership to another
 * member (SCOPE-025).
 */
class GroupMembershipController extends Controller
{
    public function leave(Request $request, CourseSpace $courseSpace, Group $group, LeaveGroup $leave): RedirectResponse
    {
        Gate::authorize('leave', $group);

        /** @var User $user */
        $user = $request->user();
        $leave->handle($user, $group);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('groups.flash.left')]);

        return to_route('courses.groups.index', $courseSpace);
    }

    public function transfer(GroupStudentRequest $request, CourseSpace $courseSpace, Group $group, TransferGroupOwnership $transfer): RedirectResponse
    {
        Gate::authorize('transferOwnership', $group);

        $transfer->handle($group, $request->studentId());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('groups.flash.transferred')]);

        return to_route('courses.groups.show', [$courseSpace, $group]);
    }
}

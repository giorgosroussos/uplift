<?php

namespace App\Modules\Courses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Courses\Actions\InviteToGroup;
use App\Modules\Courses\Actions\RespondToGroupInvitation;
use App\Modules\Courses\Actions\WithdrawGroupInvitation;
use App\Modules\Courses\Http\Requests\GroupStudentRequest;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * UC-019: members invite students and withdraw pending invitations; the
 * invitee accepts or declines (SCOPE-025, OWN-005).
 */
class GroupInvitationController extends Controller
{
    public function store(GroupStudentRequest $request, CourseSpace $courseSpace, Group $group, InviteToGroup $invite): RedirectResponse
    {
        Gate::authorize('invite', $group);

        $invite->handle($this->user($request), $group, $request->studentId());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('groups.flash.invited')]);

        return to_route('courses.groups.show', [$courseSpace, $group]);
    }

    public function accept(Request $request, CourseSpace $courseSpace, Group $group, GroupMembership $membership, RespondToGroupInvitation $respond): RedirectResponse
    {
        Gate::authorize('respond', $membership);

        $respond->accept($this->user($request), $membership);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('groups.flash.accepted')]);

        return to_route('courses.groups.show', [$courseSpace, $group]);
    }

    public function decline(Request $request, CourseSpace $courseSpace, Group $group, GroupMembership $membership, RespondToGroupInvitation $respond): RedirectResponse
    {
        Gate::authorize('respond', $membership);

        $respond->decline($this->user($request), $membership);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('groups.flash.declined')]);

        return to_route('courses.groups.index', $courseSpace);
    }

    public function destroy(CourseSpace $courseSpace, Group $group, GroupMembership $membership, WithdrawGroupInvitation $withdraw): RedirectResponse
    {
        Gate::authorize('withdraw', $membership);

        $withdraw->handle($membership);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('groups.flash.withdrawn')]);

        return to_route('courses.groups.show', [$courseSpace, $group]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * UC-019: one group with its members and pending invitations, and for its
 * members the students they may invite: enrolled in the same course space,
 * neither members of nor invited to the group (SCOPE-025).
 */
class ShowGroup
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, Group $group): array
    {
        $group->load([
            'owner:id,organization_id,name',
            'memberships' => fn ($rows) => $rows
                ->whereIn('status', GroupMembershipStatus::open())
                ->orderBy('created_at'),
            'memberships.student:id,organization_id,name',
        ]);

        return [
            'group' => ListGroups::present($user, $group),
            'invitableStudents' => Gate::forUser($user)->allows('invite', $group)
                ? $this->invitable($group)
                : [],
        ];
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function invitable(Group $group): array
    {
        return Enrolment::query()
            ->where('course_space_id', $group->course_space_id)
            ->whereNotIn('student_id', $group->memberships()
                ->whereIn('status', GroupMembershipStatus::open())
                ->select('student_id'))
            ->with('student:id,organization_id,name')
            ->get()
            ->map(fn (Enrolment $enrolment): array => [
                'id' => $enrolment->student_id,
                'name' => $enrolment->student->name,
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }
}

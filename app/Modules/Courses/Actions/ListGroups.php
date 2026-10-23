<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * UC-019 and OWN-005: the groups of a course space one user may see, each
 * with its members and pending invitations (declined, withdrawn and left
 * rows are history and not shown). A student sees the groups they belong to
 * or are invited to; the owning teacher and org admins see every group,
 * read-only. Deleted groups are absent for everybody.
 */
class ListGroups
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(User $user, CourseSpace $space): array
    {
        return $space->groups()
            ->when($user->hasRole(Role::Student), fn (Builder $groups) => $groups->whereHas(
                'memberships',
                fn (Builder $rows) => $rows
                    ->where('student_id', $user->getKey())
                    ->whereIn('status', GroupMembershipStatus::open()),
            ))
            ->with([
                'owner:id,organization_id,name',
                'memberships' => fn ($rows) => $rows
                    ->whereIn('status', GroupMembershipStatus::open())
                    ->orderBy('created_at'),
                'memberships.student:id,organization_id,name',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Group $group): array => self::present($user, $group))
            ->values()
            ->all();
    }

    /**
     * A group as the pages show it, with what the user may do with it.
     *
     * @return array<string, mixed>
     */
    public static function present(User $user, Group $group): array
    {
        $gate = Gate::forUser($user);
        $rows = $group->memberships;

        /** @var GroupMembership|null $own */
        $own = $rows->first(fn (GroupMembership $row): bool => $row->student_id === $user->getKey());

        return [
            'id' => $group->id,
            'name' => $group->name,
            'owner' => ['id' => $group->owner_id, 'name' => $group->owner->name],
            'members' => $rows->filter(fn (GroupMembership $row): bool => $row->isMember())
                ->sortBy('joined_at')
                ->map(fn (GroupMembership $row): array => [
                    'membership_id' => $row->id,
                    'student_id' => $row->student_id,
                    'name' => $row->student->name,
                    'joined_at' => $row->joined_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'invitations' => $rows->filter(fn (GroupMembership $row): bool => $row->isInvited())
                ->map(fn (GroupMembership $row): array => [
                    'membership_id' => $row->id,
                    'student_id' => $row->student_id,
                    'name' => $row->student->name,
                ])
                ->values()
                ->all(),
            'own' => $own === null ? null : ['membership_id' => $own->id, 'status' => $own->status->value],
            'can' => [
                'invite' => $gate->allows('invite', $group),
                'leave' => $gate->allows('leave', $group),
                'update' => $gate->allows('update', $group),
                'transferOwnership' => $gate->allows('transferOwnership', $group),
                'delete' => $gate->allows('delete', $group),
                'respond' => $own !== null && $own->isInvited() && $gate->allows('respond', $own),
            ],
        ];
    }
}

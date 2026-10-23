<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Events\GroupInvitationCreated;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Courses\Support\GroupLifecycle;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-019: a member invites a student enrolled in the same course space. The
 * invitation is refused when the student is not enrolled, already a member
 * or already invited; after declined, withdrawn or left a new row is created
 * and the old one stays unchanged (SCOPE-025). Dispatches
 * GroupInvitationCreated after commit.
 */
class InviteToGroup
{
    public function __construct(private readonly GroupLifecycle $lifecycle) {}

    public function handle(User $inviter, Group $group, string $studentId): GroupMembership
    {
        try {
            return DB::transaction(function () use ($inviter, $group, $studentId): GroupMembership {
                $space = CourseSpace::query()->whereKey($group->course_space_id)->sharedLock()->firstOrFail();
                $locked = $this->lifecycle->lock($group->id) ?? $this->refuse('groups.errors.group_gone');

                $student = User::query()->whereKey($studentId)->first();

                if ($student === null || ! $student->hasRole(Role::Student) || ! $space->hasEnrolled($student)) {
                    $this->refuse('groups.errors.not_enrolled');
                }

                if ($locked->memberships()->where('student_id', $studentId)->whereIn('status', GroupMembershipStatus::open())->exists()) {
                    $this->refuse('groups.errors.already_invited_or_member');
                }

                $membership = new GroupMembership;
                $membership->organization_id = $locked->organization_id;
                $membership->group_id = $locked->id;
                $membership->student_id = $studentId;
                $membership->status = GroupMembershipStatus::Invited;
                $membership->invited_by = (string) $inviter->getKey();
                $membership->save();

                GroupInvitationCreated::dispatch($membership->organization_id, $membership->id);

                return $membership;
            });
        } catch (UniqueConstraintViolationException) {
            $this->refuse('groups.errors.already_invited_or_member');
        }
    }

    private function refuse(string $key): never
    {
        throw ValidationException::withMessages(['student_id' => __($key)]);
    }
}

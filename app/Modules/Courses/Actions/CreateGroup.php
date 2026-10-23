<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-019: an enrolled student creates a group in a course space; they become
 * its owner and its first member, with joined_at (SCOPE-025). Being a member
 * of other groups of the space does not matter.
 */
class CreateGroup
{
    public function handle(User $student, CourseSpace $space, string $name): Group
    {
        return DB::transaction(function () use ($student, $space, $name): Group {
            // Share-lock the space so a concurrent archive or unenrolment
            // waits; then check the committed enrolment.
            $locked = CourseSpace::query()->whereKey($space->getKey())->sharedLock()->firstOrFail();

            if (! $locked->isActive() || ! $locked->hasEnrolled($student)) {
                throw ValidationException::withMessages(['name' => __('groups.errors.not_enrolled')]);
            }

            $group = new Group(['name' => $name]);
            $group->organization_id = $space->organization_id;
            $group->course_space_id = $space->id;
            $group->created_by = (string) $student->getKey();
            $group->owner_id = (string) $student->getKey();
            $group->save();

            $membership = new GroupMembership;
            $membership->organization_id = $group->organization_id;
            $membership->group_id = $group->id;
            $membership->student_id = (string) $student->getKey();
            $membership->status = GroupMembershipStatus::Member;
            $membership->joined_at = now();
            $membership->save();

            return $group;
        });
    }
}

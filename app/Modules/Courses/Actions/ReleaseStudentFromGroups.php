<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Courses\Support\GroupLifecycle;

/**
 * SCOPE-025: when a student's enrolment is deleted, their memberships in the
 * space become left and their pending invitations withdrawn, in the caller's
 * transaction. An unenrolled owner's ownership passes to the member with the
 * earliest joined_at; a group left without members is soft-deleted.
 */
class ReleaseStudentFromGroups
{
    public function __construct(private readonly GroupLifecycle $lifecycle) {}

    public function handle(string $courseSpaceId, string $studentId): void
    {
        $groupIds = Group::query()
            ->where('course_space_id', $courseSpaceId)
            ->whereHas('memberships', fn ($rows) => $rows
                ->where('student_id', $studentId)
                ->whereIn('status', GroupMembershipStatus::open()))
            ->orderBy('id')
            ->pluck('id');

        foreach ($groupIds as $groupId) {
            $group = $this->lifecycle->lock($groupId);

            if ($group === null) {
                continue;
            }

            /** @var GroupMembership|null $row */
            $row = $group->memberships()
                ->where('student_id', $studentId)
                ->whereIn('status', GroupMembershipStatus::open())
                ->first();

            if ($row === null) {
                continue;
            }

            if ($row->isInvited()) {
                $this->lifecycle->change($row, GroupMembershipStatus::Withdrawn);

                continue;
            }

            $this->lifecycle->change($row, GroupMembershipStatus::Left);
            $this->lifecycle->afterMemberLeft($group);
        }
    }
}

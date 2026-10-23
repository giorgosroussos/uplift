<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Support\GroupLifecycle;

/**
 * SCOPE-025 and DATA-012: archiving deletes every enrolment of the space, so
 * in the caller's transaction every membership becomes left, every pending
 * invitation withdrawn and every group soft-deleted.
 */
class DeleteGroupsOfCourseSpace
{
    public function __construct(private readonly GroupLifecycle $lifecycle) {}

    public function handle(string $courseSpaceId): void
    {
        $groupIds = Group::query()->where('course_space_id', $courseSpaceId)->orderBy('id')->pluck('id');

        foreach ($groupIds as $groupId) {
            $group = $this->lifecycle->lock($groupId);

            if ($group !== null) {
                $this->lifecycle->delete($group, releaseMembers: true);
            }
        }
    }
}

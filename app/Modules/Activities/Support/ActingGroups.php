<?php

namespace App\Modules\Activities\Support;

use App\Modules\Activities\Models\Activity;
use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Which groups may act in a group activity (SCOPE-025), read through the
 * Courses models: the acting student chooses one of the groups they are a
 * current member of in the activity's course space; the activity's
 * group_size is checked only when a group acts; and a group cannot take
 * part while any of its current members already takes part through
 * another group.
 */
final class ActingGroups
{
    /**
     * The (not deleted) groups of the activity's course space the student
     * is a current member of, by name.
     *
     * @return Collection<int, Group>
     */
    public function of(User $student, Activity $activity): Collection
    {
        return Group::query()
            ->where('course_space_id', $activity->course_space_id)
            ->whereHas('memberships', fn ($query) => $query
                ->where('student_id', $student->getKey())
                ->where('status', GroupMembershipStatus::Member))
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * The student's group with the given id, when they may act for it in
     * the activity.
     */
    public function find(User $student, Activity $activity, string $groupId): ?Group
    {
        return $this->of($student, $activity)->firstWhere('id', $groupId);
    }

    /**
     * Ids of the group's current members.
     *
     * @return list<string>
     */
    public function memberIds(Group $group): array
    {
        return GroupMembership::query()
            ->where('group_id', $group->id)
            ->where('status', GroupMembershipStatus::Member)
            ->pluck('student_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    /**
     * Whether the group has more current members than the activity allows.
     */
    public function tooLarge(Group $group, Activity $activity): bool
    {
        return $activity->group_size !== null && count($this->memberIds($group)) > $activity->group_size;
    }

    /**
     * Whether a current member of the group is a current member of one of
     * the given groups, which already take part in the activity.
     *
     * @param  list<string>  $participatingGroupIds
     */
    public function overlaps(Group $group, array $participatingGroupIds): bool
    {
        $others = array_values(array_diff($participatingGroupIds, [$group->id]));

        if ($others === []) {
            return false;
        }

        return GroupMembership::query()
            ->whereIn('group_id', $others)
            ->where('status', GroupMembershipStatus::Member)
            ->whereIn('student_id', $this->memberIds($group))
            ->exists();
    }
}

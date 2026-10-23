<?php

namespace App\Modules\Assessment\Support;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Group quiz attempts (SCOPE-012, SCOPE-025), read from the groups of
 * Courses.
 *
 * A student takes part in a group attempt while they are a current member
 * of its (not deleted) group, and keeps taking part in it when they were a
 * member when it was submitted (membership joined_at and left_at decide
 * it), even after leaving the group or its deletion (OWN-006).
 *
 * In one quiz each student takes part through at most one group, so a
 * group may not start an attempt while one of its current members takes
 * part in an attempt of another group; group_size is checked only here,
 * when the group acts.
 */
final class GroupAttempts
{
    /**
     * The groups through which the student may act in the activity: the
     * not deleted groups of its course space where they are a member.
     *
     * @return Collection<int, Group>
     */
    public function actingGroups(User $student, Activity $activity): Collection
    {
        return Group::query()
            ->where('organization_id', $student->organization_id)
            ->where('course_space_id', $activity->course_space_id)
            ->whereHas('memberships', fn (Builder $membership) => $membership
                ->where('student_id', $student->getKey())
                ->where('status', GroupMembershipStatus::Member))
            ->orderBy('name')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * The ids of the group's current members.
     *
     * @return list<string>
     */
    public function memberIds(Group $group): array
    {
        return $group->memberships()
            ->where('status', GroupMembershipStatus::Member)
            ->orderBy('student_id')
            ->pluck('student_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    /**
     * Why the group may not start an attempt of the quiz now, or null when
     * it may. Reasons are keys of assessment.attempt.refused.
     *
     * @param  list<string>  $memberIds  the group's current members
     */
    public function refusal(Quiz $quiz, Activity $activity, Group $group, array $memberIds): ?string
    {
        if ($activity->group_size !== null && count($memberIds) > $activity->group_size) {
            return 'group_too_large';
        }

        $attempts = $quiz->attempts()->where('group_id', $group->id);

        if ((clone $attempts)->where('status', AttemptStatus::InProgress->value)->exists()) {
            return 'group_in_progress';
        }

        if ($attempts->count() >= $quiz->max_attempts) {
            return 'group_no_attempts_left';
        }

        $elsewhere = $quiz->attempts()->getQuery()->where('group_id', '!=', $group->id);

        if (self::whereTakesPart($elsewhere, $memberIds)->exists()) {
            return 'other_group';
        }

        return null;
    }

    /**
     * The attempts left to the group in the quiz.
     */
    public function attemptsLeft(Quiz $quiz, Group $group): int
    {
        return max(0, $quiz->max_attempts - $quiz->attempts()->where('group_id', $group->id)->count());
    }

    /**
     * Whether the student takes part in the group attempt (see above).
     */
    public static function takesPart(User $student, QuizAttempt $attempt): bool
    {
        return $attempt->isGroupAttempt()
            && $student->organization_id === $attempt->organization_id
            && self::whereTakesPart(QuizAttempt::query()->whereKey($attempt->getKey()), [(string) $student->getKey()])->exists();
    }

    /**
     * Limits a query of attempts to the group attempts in which any of the
     * students takes part.
     *
     * @param  Builder<QuizAttempt>  $attempts
     * @param  list<string>  $studentIds
     * @return Builder<QuizAttempt>
     */
    public static function whereTakesPart(Builder $attempts, array $studentIds): Builder
    {
        return $attempts
            ->whereNotNull('quiz_attempts.group_id')
            ->whereHas('group', fn (Builder $group) => $group->whereHas(
                'memberships',
                fn (Builder $membership) => $membership
                    ->whereIn('group_memberships.student_id', $studentIds)
                    ->where(fn (Builder $when) => $when
                        // A current member of the group.
                        ->where(fn (Builder $current) => $current
                            ->where('group_memberships.status', GroupMembershipStatus::Member->value)
                            ->whereNull('groups.deleted_at'))
                        // A member when the attempt was submitted.
                        ->orWhere(fn (Builder $then) => $then
                            ->whereNotNull('quiz_attempts.submitted_at')
                            ->whereNotNull('group_memberships.joined_at')
                            ->whereColumn('group_memberships.joined_at', '<=', 'quiz_attempts.submitted_at')
                            ->where(fn (Builder $stayed) => $stayed
                                ->whereNull('group_memberships.left_at')
                                ->orWhereColumn('group_memberships.left_at', '>', 'quiz_attempts.submitted_at')))),
            ));
    }
}

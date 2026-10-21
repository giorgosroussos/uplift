<?php

namespace App\Modules\Assessment\Policies;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * OWN-006 (SEC-007): a student reads only their own attempts (group
 * attempts come with groups, PHASE-4); a teacher reads only attempts of
 * quizzes in course spaces they own; an org admin never reads individual
 * attempts, only aggregates.
 */
class QuizAttemptPolicy
{
    public function view(User $actor, QuizAttempt $attempt): bool
    {
        if (! $this->sameOrganization($actor, $attempt)) {
            return false;
        }

        return match ($actor->role) {
            Role::Student => $attempt->isOf($actor),
            Role::Teacher => $this->managesQuizOf($actor, $attempt),
            Role::OrgAdmin => false,
        };
    }

    /**
     * Answering and submitting: only the student's own attempt.
     */
    public function update(User $actor, QuizAttempt $attempt): bool
    {
        return $this->sameOrganization($actor, $attempt)
            && $actor->hasRole(Role::Student)
            && $attempt->isOf($actor);
    }

    /**
     * Whether the teacher owns the course space of the attempt's quiz.
     */
    public static function managesQuizOf(User $actor, QuizAttempt $attempt): bool
    {
        // The quiz id is the activity id (DATA-001); a deleted activity
        // still belongs to its course space.
        $space = Activity::query()->withTrashed()->find($attempt->quiz_id)?->courseSpace;

        return $space !== null && Gate::forUser($actor)->allows('manageActivities', $space);
    }

    private function sameOrganization(User $actor, QuizAttempt $attempt): bool
    {
        return $actor->canAct() && $actor->organization_id === $attempt->organization_id;
    }
}

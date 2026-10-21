<?php

namespace App\Modules\Assessment\Policies;

use App\Modules\Assessment\Models\Answer;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;

/**
 * OWN-007 (SEC-007): a student reads and gives only answers of their own
 * attempts (group attempts come with groups, PHASE-4); a teacher reads and
 * grades only answers of quizzes in course spaces they own; an org admin
 * never reads individual answers.
 */
class AnswerPolicy
{
    public function view(User $actor, Answer $answer): bool
    {
        if (! $this->sameOrganization($actor, $answer)) {
            return false;
        }

        return match ($actor->role) {
            Role::Student => $answer->attempt->isOf($actor),
            Role::Teacher => QuizAttemptPolicy::managesQuizOf($actor, $answer->attempt),
            Role::OrgAdmin => false,
        };
    }

    /**
     * Giving or changing the answer: only the student whose attempt it is.
     */
    public function update(User $actor, Answer $answer): bool
    {
        return $this->sameOrganization($actor, $answer)
            && $actor->hasRole(Role::Student)
            && $answer->attempt->isOf($actor);
    }

    /**
     * Points and feedback: only the teacher owning the quiz's course space.
     */
    public function grade(User $actor, Answer $answer): bool
    {
        return $this->sameOrganization($actor, $answer)
            && $actor->hasRole(Role::Teacher)
            && QuizAttemptPolicy::managesQuizOf($actor, $answer->attempt);
    }

    private function sameOrganization(User $actor, Answer $answer): bool
    {
        return $actor->canAct() && $actor->organization_id === $answer->organization_id;
    }
}

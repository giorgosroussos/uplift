<?php

namespace App\Modules\Assessment\Policies;

use App\Modules\Assessment\Models\Question;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;

/**
 * OWN-016 and DATA-008: a teacher reads, edits and deletes only their own
 * bank questions (in_bank = true, owner_id = self); org admins and students
 * have no access to any question bank, nor to its pages. A quiz copy
 * (in_bank = false) is not a bank question and is never reached through it.
 */
class QuestionPolicy
{
    /**
     * Open one's own question bank.
     */
    public function viewAny(User $actor): bool
    {
        return $this->isActiveTeacher($actor);
    }

    public function create(User $actor): bool
    {
        return $this->isActiveTeacher($actor);
    }

    public function view(User $actor, Question $question): bool
    {
        return $this->ownsBankQuestion($actor, $question);
    }

    public function update(User $actor, Question $question): bool
    {
        return $this->ownsBankQuestion($actor, $question);
    }

    public function delete(User $actor, Question $question): bool
    {
        return $this->ownsBankQuestion($actor, $question);
    }

    private function ownsBankQuestion(User $actor, Question $question): bool
    {
        return $this->isActiveTeacher($actor)
            && $actor->organization_id === $question->organization_id
            && $question->isBankQuestionOf($actor);
    }

    private function isActiveTeacher(User $actor): bool
    {
        return $actor->canAct() && $actor->hasRole(Role::Teacher);
    }
}

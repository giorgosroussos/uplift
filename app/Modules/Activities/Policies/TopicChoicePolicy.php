<?php

namespace App\Modules\Activities\Policies;

use App\Modules\Activities\Models\TopicChoice;
use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * OWN-008 on ENT-TopicChoice: a student sees and changes only their own
 * choice or the choice of a group they are a current member of; a teacher
 * sees the choices of their own activities only and changes none. Nobody
 * lists choices across activities. Changes go through SelectTopic and
 * CancelTopicChoice, which only ever touch the acting student's own or
 * group's choice.
 */
class TopicChoicePolicy
{
    public function viewAny(User $actor): bool
    {
        return false;
    }

    public function view(User $actor, TopicChoice $choice): bool
    {
        if (! $actor->canAct() || $actor->organization_id !== $choice->organization_id) {
            return false;
        }

        if ($actor->hasRole(Role::Student)) {
            return $this->holds($actor, $choice);
        }

        $space = $choice->topic->topicActivity->activity->courseSpace;

        return $actor->hasRole(Role::Teacher) && Gate::forUser($actor)->allows('manageActivities', $space);
    }

    public function update(User $actor, TopicChoice $choice): bool
    {
        return $this->changes($actor, $choice);
    }

    public function delete(User $actor, TopicChoice $choice): bool
    {
        return $this->changes($actor, $choice);
    }

    private function changes(User $actor, TopicChoice $choice): bool
    {
        return $actor->canAct()
            && $actor->hasRole(Role::Student)
            && $actor->organization_id === $choice->organization_id
            && $this->holds($actor, $choice);
    }

    /**
     * The student's own choice, or one of a (not deleted) group they are a
     * current member of.
     */
    private function holds(User $student, TopicChoice $choice): bool
    {
        if ($choice->student_id !== null) {
            return $choice->student_id === $student->getKey();
        }

        $group = $choice->group_id === null ? null : Group::query()->find($choice->group_id);

        return $group !== null && $group->hasMember($student);
    }
}

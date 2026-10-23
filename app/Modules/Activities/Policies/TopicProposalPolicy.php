<?php

namespace App\Modules\Activities\Policies;

use App\Modules\Activities\Models\TopicProposal;
use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * OWN-009 on ENT-TopicProposal: a student reads only their own proposals
 * or those of a group they are a current member of; a teacher reads and
 * decides only proposals of their own activities. Nobody lists proposals
 * across activities, and nobody edits or deletes a proposal: students
 * create them through ProposeTopic, the owning teacher decides them
 * through DecideTopicProposal.
 */
class TopicProposalPolicy
{
    public function viewAny(User $actor): bool
    {
        return false;
    }

    public function view(User $actor, TopicProposal $proposal): bool
    {
        if (! $actor->canAct() || $actor->organization_id !== $proposal->organization_id) {
            return false;
        }

        if ($actor->hasRole(Role::Student)) {
            return self::holds($actor, $proposal);
        }

        return $this->ownsActivity($actor, $proposal);
    }

    /**
     * Approve or reject (UC-027): the teacher who owns the activity.
     */
    public function decide(User $actor, TopicProposal $proposal): bool
    {
        return $actor->canAct()
            && $actor->organization_id === $proposal->organization_id
            && $this->ownsActivity($actor, $proposal);
    }

    public function update(User $actor, TopicProposal $proposal): bool
    {
        return false;
    }

    public function delete(User $actor, TopicProposal $proposal): bool
    {
        return false;
    }

    /**
     * The student's own proposal, or one of a (not deleted) group they are
     * a current member of.
     */
    public static function holds(User $student, TopicProposal $proposal): bool
    {
        if ($proposal->student_id !== null) {
            return $proposal->student_id === $student->getKey();
        }

        $group = $proposal->group_id === null ? null : Group::query()->find($proposal->group_id);

        return $group !== null && $group->hasMember($student);
    }

    private function ownsActivity(User $actor, TopicProposal $proposal): bool
    {
        return $actor->hasRole(Role::Teacher)
            && Gate::forUser($actor)->allows('manageActivities', $proposal->topicActivity->activity->courseSpace);
    }
}

<?php

namespace App\Modules\Activities\Policies;

use App\Modules\Activities\Models\TopicActivity;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * UC-014, UC-015, UC-027: who opens a topic selection or a topic proposal
 * activity, chooses or proposes in it, and sees who holds which topic or
 * all proposals. Opening follows the activity (ActivityPolicy::view: the
 * owning teacher and the enrolled students of an active space; a draft
 * exists only for its owner). Only an enrolled student chooses or
 * proposes; only the owning teacher sees the holders (OWN-008) and every
 * proposal (OWN-009).
 */
class TopicActivityPolicy
{
    public function view(User $actor, TopicActivity $topicActivity): Response
    {
        return Gate::forUser($actor)->inspect('view', $topicActivity->activity);
    }

    /**
     * Whether the actor is someone who chooses in the activity; whether it
     * is open and the topic has room is up to SelectTopic.
     */
    public function choose(User $actor, TopicActivity $topicActivity): bool
    {
        $activity = $topicActivity->activity;

        return $actor->canAct()
            && $actor->hasRole(Role::Student)
            && $actor->organization_id === $topicActivity->organization_id
            && ! $activity->isDraft()
            && Gate::forUser($actor)->allows('participate', $activity->courseSpace);
    }

    public function viewChoices(User $actor, TopicActivity $topicActivity): bool
    {
        return $actor->organization_id === $topicActivity->organization_id
            && Gate::forUser($actor)->allows('manageActivities', $topicActivity->activity->courseSpace);
    }

    /**
     * Whether the actor is someone who proposes topics in the activity;
     * whether it is open and the holder may propose is up to ProposeTopic.
     */
    public function propose(User $actor, TopicActivity $topicActivity): bool
    {
        return $this->choose($actor, $topicActivity);
    }

    /**
     * The owning teacher sees and decides every proposal of the activity.
     */
    public function viewProposals(User $actor, TopicActivity $topicActivity): bool
    {
        return $actor->hasRole(Role::Teacher) && $this->viewChoices($actor, $topicActivity);
    }
}

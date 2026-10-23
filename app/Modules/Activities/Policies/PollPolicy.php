<?php

namespace App\Modules\Activities\Policies;

use App\Modules\Activities\Models\Poll;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * UC-013: who opens a poll, votes in it and sees its results. Opening
 * follows the activity (ActivityPolicy::view: the owning teacher and the
 * enrolled students of an active space; a draft exists only for its
 * owner). Only an enrolled student votes. Results (counts only, SEC-009)
 * are for the owning teacher at any time and for a student as the teacher
 * set them (SCOPE-013).
 */
class PollPolicy
{
    public function view(User $actor, Poll $poll): Response
    {
        return Gate::forUser($actor)->inspect('view', $poll->activity);
    }

    /**
     * Whether the actor is someone who votes in the poll; whether the poll
     * is open and the student has not voted yet is up to CastPollVote.
     */
    public function vote(User $actor, Poll $poll): bool
    {
        $activity = $poll->activity;

        return $actor->canAct()
            && $actor->hasRole(Role::Student)
            && $actor->organization_id === $poll->organization_id
            && ! $activity->isDraft()
            && Gate::forUser($actor)->allows('participate', $activity->courseSpace);
    }

    public function viewResults(User $actor, Poll $poll): bool
    {
        if ($actor->organization_id !== $poll->organization_id) {
            return false;
        }

        $activity = $poll->activity;

        if (Gate::forUser($actor)->allows('manageActivities', $activity->courseSpace)) {
            return true;
        }

        if (! $this->vote($actor, $poll)) {
            return false;
        }

        $hasVoted = $poll->participations()->where('student_id', $actor->getKey())->exists();

        return $poll->resultsVisibleToStudent($hasVoted);
    }
}

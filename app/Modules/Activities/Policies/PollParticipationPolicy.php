<?php

namespace App\Modules\Activities\Policies;

use App\Modules\Activities\Models\PollParticipation;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;

/**
 * OWN-011 on ENT-PollParticipation: a student sees only their own
 * participation; a teacher (or admin) sees only the vote counts per option
 * and the number of participants (ShowPoll::results), never who voted or
 * what anyone voted (SEC-009). Nobody lists participations, and nobody
 * changes or removes one: a participation is written only by CastPollVote,
 * together with the vote, and the vote is final (SCOPE-013).
 */
class PollParticipationPolicy
{
    public function viewAny(User $actor): bool
    {
        return false;
    }

    public function view(User $actor, PollParticipation $participation): bool
    {
        return $actor->canAct()
            && $actor->hasRole(Role::Student)
            && $actor->organization_id === $participation->organization_id
            && $actor->getKey() === $participation->student_id;
    }

    public function update(User $actor, PollParticipation $participation): bool
    {
        return false;
    }

    public function delete(User $actor, PollParticipation $participation): bool
    {
        return false;
    }
}

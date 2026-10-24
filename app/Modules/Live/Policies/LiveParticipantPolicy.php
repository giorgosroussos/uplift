<?php

namespace App\Modules\Live\Policies;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Live\Models\LiveParticipant;
use Illuminate\Support\Facades\Gate;

/**
 * OWN-012: a student sees only their own progress and score (plus the
 * final ranking by nickname, when enabled, through ShowLiveResults); the
 * host teacher sees all participants of their session. Only the student
 * themself answers and submits; nobody else changes a participation. Org
 * admins see no individual participation.
 */
class LiveParticipantPolicy
{
    public function view(User $actor, LiveParticipant $participant): bool
    {
        if (! $this->sameOrganization($actor, $participant)) {
            return false;
        }

        return match ($actor->role) {
            Role::Student => $participant->isOf($actor),
            Role::Teacher => Gate::forUser($actor)->allows('host', $participant->session),
            Role::OrgAdmin => false,
        };
    }

    /**
     * Answering and submitting. Whether the participation still takes
     * answers is up to SaveLiveAnswer and SubmitLiveParticipation.
     */
    public function update(User $actor, LiveParticipant $participant): bool
    {
        return $this->sameOrganization($actor, $participant)
            && $actor->hasRole(Role::Student)
            && $participant->isOf($actor);
    }

    private function sameOrganization(User $actor, LiveParticipant $participant): bool
    {
        return $actor->canAct() && $actor->organization_id === $participant->organization_id;
    }
}

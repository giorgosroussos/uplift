<?php

namespace App\Modules\Live\Policies;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Live\Models\LiveAnswer;
use Illuminate\Support\Facades\Gate;

/**
 * OWN-013: a student sees only their own live answers; during the session
 * the host teacher sees only how many questions each participant has
 * answered (ShowLiveProgress, never an answer), after it finishes the
 * scores and answers of their own session. Only the student themself
 * changes their answers. Org admins see no individual answer.
 */
class LiveAnswerPolicy
{
    public function view(User $actor, LiveAnswer $answer): bool
    {
        if (! $this->sameOrganization($actor, $answer)) {
            return false;
        }

        $participant = $answer->participant;

        return match ($actor->role) {
            Role::Student => $participant->isOf($actor),
            Role::Teacher => $participant->session->isFinished()
                && Gate::forUser($actor)->allows('host', $participant->session),
            Role::OrgAdmin => false,
        };
    }

    public function update(User $actor, LiveAnswer $answer): bool
    {
        return $this->sameOrganization($actor, $answer)
            && Gate::forUser($actor)->allows('update', $answer->participant);
    }

    private function sameOrganization(User $actor, LiveAnswer $answer): bool
    {
        return $actor->canAct() && $actor->organization_id === $answer->organization_id;
    }
}

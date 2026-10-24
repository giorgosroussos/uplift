<?php

namespace App\Modules\Live\Policies;

use App\Modules\Activities\Models\Activity;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Live\Models\LiveSession;
use Illuminate\Support\Facades\Gate;

/**
 * Who runs and who plays a live quiz (UC-024, UC-025):
 *
 * - open: the teacher owning the quiz's course space opens a lobby;
 * - host: the host teacher of a session starts or closes it and monitors
 *   it; they must still own the course space;
 * - join: only a student enrolled in the quiz's active course space
 *   (SEC-005); never anonymous or by PIN.
 *
 * Org admins never take part in or watch individual sessions.
 */
class LiveSessionPolicy
{
    public function open(User $actor, Activity $activity): bool
    {
        return $actor->canAct()
            && $actor->hasRole(Role::Teacher)
            && $actor->organization_id === $activity->organization_id
            && Gate::forUser($actor)->allows('manageActivities', $activity->courseSpace);
    }

    public function host(User $actor, LiveSession $session): bool
    {
        if (! $this->sameOrganization($actor, $session) || ! $actor->hasRole(Role::Teacher)) {
            return false;
        }

        $space = self::space($session);

        return $session->host_id === $actor->getKey()
            && $space !== null
            && Gate::forUser($actor)->allows('manageActivities', $space);
    }

    public function join(User $actor, Activity $activity): bool
    {
        return $actor->canAct()
            && $actor->hasRole(Role::Student)
            && $actor->organization_id === $activity->organization_id
            && ! $activity->isDraft()
            && Gate::forUser($actor)->allows('participate', $activity->courseSpace);
    }

    /**
     * The course space of the session's quiz; a deleted activity still
     * belongs to its course space.
     */
    public static function space(LiveSession $session): ?CourseSpace
    {
        // The quiz id is the activity id (DATA-001).
        return Activity::query()->withTrashed()->find($session->quiz_id)?->courseSpace;
    }

    private function sameOrganization(User $actor, LiveSession $session): bool
    {
        return $actor->canAct() && $actor->organization_id === $session->organization_id;
    }
}

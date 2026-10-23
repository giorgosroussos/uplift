<?php

namespace App\Modules\Activities\Policies;

use App\Modules\Activities\Models\Activity;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Activities follow the abilities of their course space (OWN-004,
 * SCOPE-007): only the owning teacher creates, edits, publishes and closes
 * an activity and reviews its submissions (manageActivities); enrolled
 * students of an active space see its published and closed activities
 * (participate). A draft does not exist for anyone but the owning teacher
 * (404 Not Found, UC-022); org admins see no individual activities.
 */
class ActivityPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->canAct();
    }

    /**
     * A new activity goes into an active course space the teacher owns.
     */
    public function create(User $actor, CourseSpace $space): bool
    {
        return $space->isActive() && $this->manages($actor, $space);
    }

    public function view(User $actor, Activity $activity): Response
    {
        $space = $this->space($actor, $activity);

        if ($space === null) {
            return Response::denyAsNotFound();
        }

        if ($this->manages($actor, $space)) {
            return Response::allow();
        }

        if ($activity->isDraft()) {
            return Response::denyAsNotFound();
        }

        return Gate::forUser($actor)->inspect('participate', $space);
    }

    /**
     * The wizard edits drafts and published activities; a closed activity
     * is final.
     */
    public function update(User $actor, Activity $activity): Response
    {
        return $this->managing($actor, $activity, ! $activity->isClosed());
    }

    public function publish(User $actor, Activity $activity): Response
    {
        return $this->managing($actor, $activity, $activity->isDraft());
    }

    public function close(User $actor, Activity $activity): Response
    {
        return $this->managing($actor, $activity, $activity->isPublished());
    }

    /**
     * UC-040 (SCOPE-029): only the owning teacher deletes an activity, in
     * any status; DeleteActivity refuses a published or closed activity
     * that has submissions.
     */
    public function delete(User $actor, Activity $activity): Response
    {
        return $this->managing($actor, $activity, true);
    }

    /**
     * UC-028: the submissions overview, for the owning teacher only.
     */
    public function viewSubmissions(User $actor, Activity $activity): Response
    {
        return $this->managing($actor, $activity, true);
    }

    private function managing(User $actor, Activity $activity, bool $statusAllows): Response
    {
        $space = $this->space($actor, $activity);

        if ($space === null) {
            return Response::denyAsNotFound();
        }

        if (! $this->manages($actor, $space)) {
            // A draft does not exist for anyone but its owner.
            return $activity->isDraft() ? Response::denyAsNotFound() : Response::deny();
        }

        return $statusAllows ? Response::allow() : Response::deny();
    }

    private function manages(User $actor, CourseSpace $space): bool
    {
        return Gate::forUser($actor)->allows('manageActivities', $space);
    }

    private function space(User $actor, Activity $activity): ?CourseSpace
    {
        if ($actor->organization_id !== $activity->organization_id) {
            return null;
        }

        return $activity->courseSpace;
    }
}

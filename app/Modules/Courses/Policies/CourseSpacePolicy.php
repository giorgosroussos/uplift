<?php

namespace App\Modules\Courses\Policies;

use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * OWN-004 and SCOPE-007: only the owning teacher edits a course space and its
 * activities; an org admin views every course space of their organisation
 * but edits none. A teacher sees only the spaces they own (so never the
 * students of another teacher's space, OWN-003); a student sees active spaces
 * of the catalogue and the spaces they are enrolled in.
 *
 * DATA-012: an archived space, its activities and its announcements do not
 * exist for students: every ability on it is denied as 404 Not Found. Only
 * the owning teacher and org admins see it.
 *
 * Abilities other modules use through the Gate:
 * - manageActivities: create and edit activities of the space (owner only);
 * - participate: submit to the space's activities (enrolled, space active).
 */
class CourseSpacePolicy
{
    /**
     * Runs before every ability that is given a course space.
     */
    public function before(User $actor, string $ability, mixed ...$arguments): ?Response
    {
        $space = $arguments[0] ?? null;

        if ($space instanceof CourseSpace && $space->isArchived() && $actor->hasRole(Role::Student)) {
            return Response::denyAsNotFound();
        }

        return null;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->canAct();
    }

    /**
     * The catalogue of active course spaces is for students (UC-011).
     */
    public function browseCatalogue(User $actor): bool
    {
        return $actor->canAct() && $actor->hasRole(Role::Student);
    }

    public function view(User $actor, CourseSpace $space): bool
    {
        if (! $this->sameOrganization($actor, $space)) {
            return false;
        }

        return match ($actor->role) {
            Role::OrgAdmin => true,
            Role::Teacher => $space->isOwnedBy($actor),
            Role::Student => $space->isActive(),
        };
    }

    public function create(User $actor): bool
    {
        return $actor->canAct() && $actor->hasRole(Role::Teacher);
    }

    /**
     * Edit, archive and restore.
     */
    public function update(User $actor, CourseSpace $space): bool
    {
        return $this->owns($actor, $space);
    }

    /**
     * See, add and remove the enrolled students (UC-021).
     */
    public function manageEnrolments(User $actor, CourseSpace $space): bool
    {
        return $this->owns($actor, $space);
    }

    public function manageActivities(User $actor, CourseSpace $space): bool
    {
        return $this->owns($actor, $space);
    }

    /**
     * Post, edit and delete announcements of the space (UC-008, SCOPE-007).
     */
    public function manageAnnouncements(User $actor, CourseSpace $space): bool
    {
        return $this->owns($actor, $space);
    }

    /**
     * Read the announcements of the space (UC-008, DATA-013): its owning
     * teacher and its enrolled students; students never once the space is
     * archived (DATA-012).
     */
    public function viewAnnouncements(User $actor, CourseSpace $space): bool
    {
        return $this->owns($actor, $space) || $this->participate($actor, $space);
    }

    /**
     * A student enrols only in an active space of their organisation; the
     * enrolment key is checked by the action.
     */
    public function enrol(User $actor, CourseSpace $space): bool
    {
        return $this->sameOrganization($actor, $space)
            && $actor->hasRole(Role::Student)
            && $space->isActive();
    }

    public function participate(User $actor, CourseSpace $space): bool
    {
        return $this->sameOrganization($actor, $space)
            && $actor->hasRole(Role::Student)
            && $space->isActive()
            && $space->hasEnrolled($actor);
    }

    private function owns(User $actor, CourseSpace $space): bool
    {
        return $this->sameOrganization($actor, $space)
            && $actor->hasRole(Role::Teacher)
            && $space->isOwnedBy($actor);
    }

    private function sameOrganization(User $actor, CourseSpace $space): bool
    {
        return $actor->canAct() && $actor->organization_id === $space->organization_id;
    }
}

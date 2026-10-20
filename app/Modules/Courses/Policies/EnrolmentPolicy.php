<?php

namespace App\Modules\Courses\Policies;

use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;

/**
 * OWN-003: a student sees only their own enrolments; a teacher sees
 * enrolments only of course spaces they own. Only the owning teacher removes
 * an enrolment (UC-021); nobody edits one.
 */
class EnrolmentPolicy
{
    public function view(User $actor, Enrolment $enrolment): bool
    {
        if (! $this->sameOrganization($actor, $enrolment)) {
            return false;
        }

        return match ($actor->role) {
            Role::Student => $enrolment->student_id === $actor->getKey(),
            Role::Teacher => $this->ownsSpace($actor, $enrolment),
            Role::OrgAdmin => false,
        };
    }

    public function update(User $actor, Enrolment $enrolment): bool
    {
        return false;
    }

    public function delete(User $actor, Enrolment $enrolment): bool
    {
        return $this->sameOrganization($actor, $enrolment)
            && $actor->hasRole(Role::Teacher)
            && $this->ownsSpace($actor, $enrolment);
    }

    private function ownsSpace(User $actor, Enrolment $enrolment): bool
    {
        /** @var CourseSpace|null $space */
        $space = $enrolment->courseSpace;

        return $space !== null && $space->isOwnedBy($actor);
    }

    private function sameOrganization(User $actor, Enrolment $enrolment): bool
    {
        return $actor->canAct() && $actor->organization_id === $enrolment->organization_id;
    }
}

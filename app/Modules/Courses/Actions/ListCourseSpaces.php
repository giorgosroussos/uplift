<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * "My course spaces": the spaces a student is enrolled in, the spaces a
 * teacher owns, or every space of the organisation for an org admin
 * (OWN-003, OWN-004). Only course spaces of the current tenant are read.
 */
class ListCourseSpaces
{
    /**
     * @return list<array{id: string, title: string, description: string|null, status: string, enrolment_policy: string, teacher: string, enrolments_count: int|null}>
     */
    public function handle(User $user): array
    {
        $query = CourseSpace::query()
            ->with('teacher:id,organization_id,name')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('title');

        match ($user->role) {
            Role::Student => $query->whereHas(
                'enrolments',
                fn (Builder $enrolments) => $enrolments->where('student_id', $user->getKey()),
            ),
            Role::Teacher => $query->where('teacher_id', $user->getKey())->withCount('enrolments'),
            Role::OrgAdmin => $query->withCount('enrolments'),
        };

        return $query->get()
            ->map(fn (CourseSpace $space): array => [
                'id' => $space->id,
                'title' => $space->title,
                'description' => $space->description,
                'status' => $space->status->value,
                'enrolment_policy' => $space->enrolment_policy->value,
                'teacher' => $space->teacher->name,
                'enrolments_count' => $user->hasRole(Role::Student) ? null : (int) $space->getAttribute('enrolments_count'),
            ])
            ->values()
            ->all();
    }
}

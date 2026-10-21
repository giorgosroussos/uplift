<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Enums\ActivityStatus;
use App\Modules\Activities\Models\Activity;
use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The activities a user may see: a teacher every activity of the course
 * spaces they own, drafts included; a student the published and closed
 * activities of the active course spaces they are enrolled in, never a
 * draft (UC-022). Org admins see no individual activities.
 */
class ListActivities
{
    /**
     * @return list<array{id: string, title: string, type: string, status: string, course_space_id: string, course_space_title: string, deadline_at: string|null, published_at: string|null}>
     */
    public function handle(User $user, ?string $courseSpaceId = null): array
    {
        $query = Activity::query()->with('courseSpace:id,organization_id,title');

        match ($user->role) {
            Role::Teacher => $query->whereIn('course_space_id', CourseSpace::query()
                ->where('teacher_id', $user->getKey())
                ->select('id')),
            Role::Student => $query
                ->where('status', '!=', ActivityStatus::Draft)
                ->whereIn('course_space_id', CourseSpace::query()
                    ->where('status', CourseSpaceStatus::Active)
                    ->whereHas('enrolments', fn (Builder $enrolments) => $enrolments->where('student_id', $user->getKey()))
                    ->select('id')),
            Role::OrgAdmin => $query->whereRaw('false'),
        };

        if ($courseSpaceId !== null) {
            $query->where('course_space_id', $courseSpaceId);
        }

        return $query
            ->orderByRaw('deadline_at IS NULL, deadline_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Activity $activity): array => [
                'id' => $activity->id,
                'title' => $activity->title,
                'type' => $activity->type->value,
                'status' => $activity->status->value,
                'course_space_id' => $activity->course_space_id,
                'course_space_title' => $activity->courseSpace->title,
                'deadline_at' => $activity->deadline_at?->toIso8601String(),
                'published_at' => $activity->published_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}

<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * UC-011: the catalogue of active course spaces of the student's
 * organisation, searched by title or description and filtered by teacher.
 * The tenant scope keeps other organisations' spaces out.
 */
class BrowseCatalogue
{
    public const int PER_PAGE = 20;

    /**
     * @return array{spaces: LengthAwarePaginator<int, array{id: string, title: string, description: string|null, enrolment_policy: string, teacher: string, enrolled: bool}>, teachers: list<array{id: string, name: string}>}
     */
    public function handle(User $student, ?string $search, ?string $teacherId): array
    {
        $active = CourseSpace::query()->where('status', CourseSpaceStatus::Active);

        $spaces = (clone $active)
            ->with('teacher:id,organization_id,name')
            ->withExists(['enrolments as enrolled' => fn (Builder $enrolments) => $enrolments->where('student_id', $student->getKey())])
            ->when(filled($search), function (Builder $query) use ($search): void {
                $term = '%'.addcslashes(trim((string) $search), '\\%_').'%';
                $query->where(fn (Builder $match) => $match
                    ->where('title', 'ilike', $term)
                    ->orWhere('description', 'ilike', $term));
            })
            ->when(filled($teacherId), fn (Builder $query) => $query->where('teacher_id', $teacherId))
            ->orderBy('title')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (CourseSpace $space): array => [
                'id' => $space->id,
                'title' => $space->title,
                'description' => $space->description,
                'enrolment_policy' => $space->enrolment_policy->value,
                'teacher' => $space->teacher->name,
                'enrolled' => (bool) $space->getAttribute('enrolled'),
            ]);

        $teachers = User::query()
            ->whereIn('id', (clone $active)->select('teacher_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $teacher): array => ['id' => $teacher->id, 'name' => $teacher->name])
            ->values()
            ->all();

        return ['spaces' => $spaces, 'teachers' => $teachers];
    }
}

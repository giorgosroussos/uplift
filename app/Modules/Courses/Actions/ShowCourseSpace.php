<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * What one user may see of a course space: the enrolment key and the
 * enrolled students only for the owning teacher (OWN-003, OWN-004), the
 * number of enrolled students for an org admin, the own enrolment for a
 * student. Announcements only for the owning teacher and enrolled students
 * (UC-008).
 */
class ShowCourseSpace
{
    public function __construct(private readonly ListAnnouncements $announcements) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, CourseSpace $space): array
    {
        $gate = Gate::forUser($user);
        $canManage = $gate->allows('manageEnrolments', $space);

        $props = [
            'space' => [
                'id' => $space->id,
                'title' => $space->title,
                'description' => $space->description,
                'status' => $space->status->value,
                'enrolment_policy' => $space->enrolment_policy->value,
                'enrolment_key' => $gate->allows('update', $space) ? $space->enrolment_key : null,
                'teacher' => $space->teacher->name,
            ],
            'can' => [
                'update' => $gate->allows('update', $space),
                'enrol' => $gate->allows('enrol', $space),
                'manageEnrolments' => $canManage,
                'manageAnnouncements' => $gate->allows('manageAnnouncements', $space),
            ],
            'enrolled' => $user->hasRole(Role::Student) && $space->hasEnrolled($user),
            'announcements' => $this->announcements->handle($user, $space),
            'enrolmentsCount' => $user->hasRole(Role::Student) ? null : $space->enrolments()->count(),
            'students' => [],
            'availableStudents' => [],
        ];

        if ($canManage) {
            $props['students'] = $space->enrolments()
                ->with('student:id,organization_id,name,email')
                ->orderBy('enrolled_at')
                ->get()
                ->map(fn (Enrolment $enrolment): array => [
                    'enrolment_id' => $enrolment->id,
                    'name' => $enrolment->student->name,
                    'email' => $enrolment->student->email,
                    'enrolled_at' => $enrolment->enrolled_at->toIso8601String(),
                ])
                ->values()
                ->all();

            $props['availableStudents'] = User::query()
                ->where('role', Role::Student)
                ->where('is_active', true)
                ->whereNotIn('id', $space->enrolments()->select('student_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $student): array => ['id' => $student->id, 'name' => $student->name, 'email' => $student->email])
                ->values()
                ->all();
        }

        return $props;
    }
}

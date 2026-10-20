<?php

namespace App\Modules\Courses\Database\Factories;

use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Enrolments of the current tenant. Use ->for($space, 'courseSpace') and
 * ->for($student, 'student') for specific ones.
 *
 * @extends Factory<Enrolment>
 */
class EnrolmentFactory extends Factory
{
    protected $model = Enrolment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_space_id' => fn (): string => (string) CourseSpace::factory()->create()->getKey(),
            'organization_id' => fn (array $attributes): string => CourseSpace::query()
                ->withoutGlobalScopes()
                ->whereKey($attributes['course_space_id'])
                ->value('organization_id'),
            'student_id' => fn (array $attributes): string => (string) User::factory()->student()->create([
                'organization_id' => $attributes['organization_id'],
            ])->getKey(),
            'enrolled_at' => now(),
        ];
    }
}

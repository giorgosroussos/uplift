<?php

namespace App\Modules\Courses\Database\Factories;

use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Enums\EnrolmentPolicy;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Open course spaces of the current tenant, owned by a new teacher unless
 * ->for($teacher, 'teacher') is given.
 *
 * @extends Factory<CourseSpace>
 */
class CourseSpaceFactory extends Factory
{
    protected $model = CourseSpace::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_id' => fn (): string => (string) User::factory()->teacher()->create()->getKey(),
            'organization_id' => fn (array $attributes): string => User::query()
                ->withoutGlobalScopes()
                ->whereKey($attributes['teacher_id'])
                ->value('organization_id'),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'enrolment_policy' => EnrolmentPolicy::Open,
            'enrolment_key' => null,
        ];
    }

    public function withKey(string $key = 'K7P2Q'): static
    {
        return $this->state([
            'enrolment_policy' => EnrolmentPolicy::Key,
            'enrolment_key' => $key,
        ]);
    }

    /**
     * Created active (the initial state), then archived through the state model.
     */
    public function archived(): static
    {
        return $this->afterCreating(function (CourseSpace $space): void {
            $space->transitionTo(CourseSpaceStatus::Archived);
        });
    }
}

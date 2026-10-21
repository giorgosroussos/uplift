<?php

namespace App\Modules\Activities\Database\Factories;

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\ActivityStatus;
use App\Modules\Activities\Models\Activity;
use App\Modules\Courses\Models\CourseSpace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Draft quizzes of the current tenant in a new course space unless
 * ->for($space, 'courseSpace') is given.
 *
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

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
            'type' => ActivityKind::Quiz,
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'deadline_at' => null,
            'is_group' => false,
            'group_size' => null,
        ];
    }

    public function ofKind(ActivityKind $kind): static
    {
        return $this->state(['type' => $kind]);
    }

    /**
     * Created as draft (the initial state), then published through the state model.
     */
    public function published(): static
    {
        return $this->afterCreating(function (Activity $activity): void {
            $activity->transitionTo(ActivityStatus::Published);
        });
    }

    public function closed(): static
    {
        return $this->afterCreating(function (Activity $activity): void {
            $activity->transitionTo(ActivityStatus::Published);
            $activity->transitionTo(ActivityStatus::Closed);
        });
    }
}

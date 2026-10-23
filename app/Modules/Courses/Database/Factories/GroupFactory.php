<?php

namespace App\Modules\Courses\Database\Factories;

use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Groups of the current tenant, without memberships. Use
 * ->for($space, 'courseSpace') and the owner state for specific ones; the
 * CreateGroup action also records the creator's membership.
 *
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

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
            'name' => fake()->words(2, true),
            'created_by' => fn (array $attributes): string => (string) User::factory()->student()->create([
                'organization_id' => $attributes['organization_id'],
            ])->getKey(),
            'owner_id' => fn (array $attributes): string => $attributes['created_by'],
        ];
    }

    public function ownedBy(User $student): static
    {
        return $this->state([
            'created_by' => (string) $student->getKey(),
            'owner_id' => (string) $student->getKey(),
        ]);
    }
}

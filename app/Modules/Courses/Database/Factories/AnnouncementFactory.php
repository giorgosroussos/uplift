<?php

namespace App\Modules\Courses\Database\Factories;

use App\Modules\Courses\Models\Announcement;
use App\Modules\Courses\Models\CourseSpace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Published announcements of the current tenant, authored by the owning
 * teacher of the course space. Use ->for($space, 'courseSpace').
 *
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $space = fn (array $attributes): CourseSpace => CourseSpace::query()
            ->withoutGlobalScopes()
            ->findOrFail($attributes['course_space_id']);

        return [
            'course_space_id' => fn (): string => (string) CourseSpace::factory()->create()->getKey(),
            'organization_id' => fn (array $attributes): string => $space($attributes)->organization_id,
            'author_id' => fn (array $attributes): string => $space($attributes)->teacher_id,
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'published_at' => now(),
        ];
    }
}

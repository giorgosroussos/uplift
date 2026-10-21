<?php

namespace App\Modules\Notifications\Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Enums\NotificationKind;
use App\Modules\Notifications\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Unread announcement_published notifications of the current tenant.
 * Use ->for($user).
 *
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fn (): string => (string) User::factory()->student()->create()->getKey(),
            'organization_id' => fn (array $attributes): string => User::query()
                ->withoutGlobalScopes()
                ->findOrFail($attributes['user_id'])
                ->organization_id,
            'kind' => NotificationKind::AnnouncementPublished,
            'data' => [
                'course_space_id' => (string) Str::uuid(),
                'announcement_id' => (string) Str::uuid(),
                'course_space_title' => fake()->words(2, true),
                'announcement_title' => fake()->sentence(4),
            ],
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }
}

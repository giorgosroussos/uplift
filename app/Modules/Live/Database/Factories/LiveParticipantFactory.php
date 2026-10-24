<?php

namespace App\Modules\Live\Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Live\Models\LiveParticipant;
use App\Modules\Live\Models\LiveSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Participants who joined now, of a new lobby and a new student unless
 * ->for($session, 'session') / ->for($student, 'student') are given.
 *
 * @extends Factory<LiveParticipant>
 */
class LiveParticipantFactory extends Factory
{
    protected $model = LiveParticipant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'live_session_id' => fn (): string => (string) LiveSession::factory()->create()->getKey(),
            'organization_id' => fn (array $attributes): string => LiveSession::query()
                ->withoutGlobalScopes()
                ->whereKey($attributes['live_session_id'])
                ->value('organization_id'),
            'student_id' => fn (): string => (string) User::factory()->student()->create()->getKey(),
            'joined_at' => now(),
        ];
    }

    public function submitted(): static
    {
        return $this->state(['submitted_at' => now()]);
    }
}

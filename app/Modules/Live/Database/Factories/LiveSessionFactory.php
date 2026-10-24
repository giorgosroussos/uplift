<?php

namespace App\Modules\Live\Database\Factories;

use App\Modules\Assessment\Models\Quiz;
use App\Modules\Identity\Models\User;
use App\Modules\Live\Enums\LiveSessionStatus;
use App\Modules\Live\Models\LiveSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Lobbies of a new live quiz hosted by a new teacher unless
 * ->for($quiz, 'quiz') / ->for($teacher, 'host') are given; running(),
 * finished() and cancelled() move them on through the state model
 * (running from now for the quiz duration).
 *
 * @extends Factory<LiveSession>
 */
class LiveSessionFactory extends Factory
{
    protected $model = LiveSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_id' => fn (): string => (string) Quiz::factory()->live()->create()->getKey(),
            'organization_id' => fn (array $attributes): string => Quiz::query()
                ->withoutGlobalScopes()
                ->whereKey($attributes['quiz_id'])
                ->value('organization_id'),
            'host_id' => fn (): string => (string) User::factory()->teacher()->create()->getKey(),
        ];
    }

    public function running(): static
    {
        return $this->afterCreating(function (LiveSession $session): void {
            $session->transitionTo(LiveSessionStatus::Running);
            $session->started_at = now()->toImmutable();
            $session->ends_at = $session->started_at->addSeconds($session->quiz->time_limit ?? 900);
            $session->save();
        });
    }

    public function finished(): static
    {
        return $this->running()->afterCreating(function (LiveSession $session): void {
            $session->transitionTo(LiveSessionStatus::Finished);
            $session->finished_at = now()->toImmutable();
            $session->save();
        });
    }

    public function cancelled(): static
    {
        return $this->afterCreating(function (LiveSession $session): void {
            $session->transitionTo(LiveSessionStatus::Cancelled);
            $session->save();
        });
    }
}

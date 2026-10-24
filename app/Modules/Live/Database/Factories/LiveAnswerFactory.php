<?php

namespace App\Modules\Live\Database\Factories;

use App\Modules\Live\Models\LiveAnswer;
use App\Modules\Live\Models\LiveParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Unscored answers with no options selected; give ->for($participant,
 * 'participant') and a quiz_question_id of the participant's quiz.
 *
 * @extends Factory<LiveAnswer>
 */
class LiveAnswerFactory extends Factory
{
    protected $model = LiveAnswer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'live_participant_id' => fn (): string => (string) LiveParticipant::factory()->create()->getKey(),
            'organization_id' => fn (array $attributes): string => LiveParticipant::query()
                ->withoutGlobalScopes()
                ->whereKey($attributes['live_participant_id'])
                ->value('organization_id'),
            'response' => ['option_ids' => []],
            'answered_at' => now(),
        ];
    }
}

<?php

namespace App\Modules\Assessment\Database\Factories;

use App\Modules\Assessment\Models\MatchingPair;
use App\Modules\Assessment\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An extra pair of a question of the current tenant; a new free-text
 * question unless ->for($question, 'question') is given.
 *
 * @extends Factory<MatchingPair>
 */
class MatchingPairFactory extends Factory
{
    protected $model = MatchingPair::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $question = fn (array $attributes): Question => Question::query()
            ->withoutGlobalScopes()
            ->findOrFail($attributes['question_id']);

        return [
            'question_id' => fn (): string => (string) Question::factory()->freeText()->create()->getKey(),
            'organization_id' => fn (array $attributes): string => $question($attributes)->organization_id,
            'left_text' => fake()->word(),
            'right_text' => fake()->word(),
            'position' => fn (array $attributes): int => MatchingPair::query()
                ->withoutGlobalScopes()
                ->where('question_id', $attributes['question_id'])
                ->count(),
        ];
    }
}

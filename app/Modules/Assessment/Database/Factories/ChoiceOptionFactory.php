<?php

namespace App\Modules\Assessment\Database\Factories;

use App\Modules\Assessment\Models\ChoiceOption;
use App\Modules\Assessment\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An extra option of a question of the current tenant; a new free-text
 * question unless ->for($question, 'question') is given.
 *
 * @extends Factory<ChoiceOption>
 */
class ChoiceOptionFactory extends Factory
{
    protected $model = ChoiceOption::class;

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
            'text' => fake()->words(2, true),
            'is_correct' => false,
            'position' => fn (array $attributes): int => ChoiceOption::query()
                ->withoutGlobalScopes()
                ->where('question_id', $attributes['question_id'])
                ->count(),
        ];
    }
}

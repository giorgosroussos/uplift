<?php

namespace App\Modules\Assessment\Database\Factories;

use App\Modules\Assessment\Enums\GradingStatus;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pending answers of a new attempt; the question is the first one of the
 * attempt's quiz unless quiz_question_id is given. graded() moves an
 * answer through the state model.
 *
 * @extends Factory<Answer>
 */
class AnswerFactory extends Factory
{
    protected $model = Answer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_id' => fn (): string => (string) QuizAttempt::factory()->create()->getKey(),
            'organization_id' => fn (array $attributes): string => QuizAttempt::query()
                ->withoutGlobalScopes()
                ->whereKey($attributes['attempt_id'])
                ->value('organization_id'),
            'quiz_question_id' => fn (array $attributes): string => (string) QuizQuestion::query()
                ->withoutGlobalScopes()
                ->where('quiz_id', QuizAttempt::query()->withoutGlobalScopes()->whereKey($attributes['attempt_id'])->value('quiz_id'))
                ->orderBy('position')
                ->value('id'),
            'text_response' => fake()->sentence(),
        ];
    }

    public function graded(): static
    {
        return $this->afterCreating(function (Answer $answer): void {
            $answer->points_awarded = '1.00';
            $answer->graded_at = now();
            $answer->transitionTo(GradingStatus::Graded);
        });
    }
}

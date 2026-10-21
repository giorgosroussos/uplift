<?php

namespace App\Modules\Assessment\Database\Factories;

use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * In-progress attempts of a new student on a new quiz unless
 * ->for($quiz, 'quiz') / ->for($student, 'student') are given; submitted()
 * and graded() move them on through the state model.
 *
 * @extends Factory<QuizAttempt>
 */
class QuizAttemptFactory extends Factory
{
    protected $model = QuizAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_id' => fn (): string => (string) Quiz::factory()->create()->getKey(),
            'organization_id' => fn (array $attributes): string => Quiz::query()
                ->withoutGlobalScopes()
                ->whereKey($attributes['quiz_id'])
                ->value('organization_id'),
            'student_id' => fn (): string => (string) User::factory()->student()->create()->getKey(),
            'started_at' => now(),
        ];
    }

    public function submitted(): static
    {
        return $this->afterCreating(function (QuizAttempt $attempt): void {
            $attempt->submitted_at = now();
            $attempt->submitted_by = $attempt->student_id;
            $attempt->transitionTo(AttemptStatus::Submitted);
        });
    }

    public function graded(): static
    {
        return $this->submitted()->afterCreating(function (QuizAttempt $attempt): void {
            $attempt->transitionTo(AttemptStatus::Graded);
        });
    }
}

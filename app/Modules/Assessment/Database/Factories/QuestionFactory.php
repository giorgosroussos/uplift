<?php

namespace App\Modules\Assessment\Database\Factories;

use App\Modules\Assessment\Enums\QuestionType;
use App\Modules\Assessment\Models\ChoiceOption;
use App\Modules\Assessment\Models\MatchingPair;
use App\Modules\Assessment\Models\Question;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Valid bank questions (DATA-016) of the current tenant, owned by a new
 * teacher unless ->for($teacher, 'owner') is given. The default is a
 * single-choice question with options A (correct) and B.
 *
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => fn (): string => (string) User::factory()->teacher()->create()->getKey(),
            'organization_id' => fn (array $attributes): string => User::query()
                ->withoutGlobalScopes()
                ->whereKey($attributes['owner_id'])
                ->value('organization_id'),
            'type' => QuestionType::SingleChoice,
            'prompt' => fake()->sentence().'?',
            'points' => '1.00',
            'in_bank' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Question $question): void {
            if ($question->options()->exists() || $question->pairs()->exists()) {
                return;
            }

            match ($question->type) {
                QuestionType::SingleChoice, QuestionType::MultipleChoice => $this->addOptions($question, [['A', true], ['B', false]]),
                QuestionType::TrueFalse => $this->addOptions($question, [['true', true], ['false', false]]),
                QuestionType::Matching => $this->addPairs($question, [['France', 'Paris'], ['Greece', 'Athens']]),
                QuestionType::FreeText => null,
            };
        });
    }

    public function ofType(QuestionType $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function multipleChoice(): static
    {
        return $this->ofType(QuestionType::MultipleChoice);
    }

    /**
     * Options 'true' (correct) and 'false'.
     */
    public function trueFalse(): static
    {
        return $this->ofType(QuestionType::TrueFalse);
    }

    /**
     * Pairs France-Paris and Greece-Athens.
     */
    public function matching(): static
    {
        return $this->ofType(QuestionType::Matching);
    }

    public function freeText(): static
    {
        return $this->ofType(QuestionType::FreeText);
    }

    /**
     * Single or multiple choice with the given options, in order.
     *
     * @param  list<array{0: string, 1: bool}>  $options  text, is_correct
     */
    public function withOptions(array $options): static
    {
        return $this->afterCreating(function (Question $question) use ($options): void {
            // Replaces the default options added by configure().
            $question->options()->delete();
            $this->addOptions($question, $options);
        });
    }

    /**
     * A matching question with the given pairs, in order.
     *
     * @param  list<array{0: string, 1: string}>  $pairs  left, right
     */
    public function withPairs(array $pairs): static
    {
        return $this->matching()->afterCreating(function (Question $question) use ($pairs): void {
            // Replaces the default pairs added by configure().
            $question->pairs()->delete();
            $this->addPairs($question, $pairs);
        });
    }

    /**
     * A copy placed in a quiz, not part of any bank (DATA-008).
     */
    public function quizCopy(): static
    {
        return $this->state(['in_bank' => false]);
    }

    /**
     * @param  list<array{0: string, 1: bool}>  $options
     */
    private function addOptions(Question $question, array $options): void
    {
        foreach ($options as $position => [$text, $isCorrect]) {
            $option = new ChoiceOption(['text' => $text, 'is_correct' => $isCorrect, 'position' => $position]);
            $option->organization_id = $question->organization_id;
            $option->question_id = $question->id;
            $option->save();
        }
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pairs
     */
    private function addPairs(Question $question, array $pairs): void
    {
        foreach ($pairs as $position => [$left, $right]) {
            $pair = new MatchingPair(['left_text' => $left, 'right_text' => $right, 'position' => $position]);
            $pair->organization_id = $question->organization_id;
            $pair->question_id = $question->id;
            $pair->save();
        }
    }
}

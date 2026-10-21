<?php

namespace App\Modules\Assessment\Http\Requests;

use App\Modules\Assessment\Enums\QuestionType;
use App\Modules\Assessment\Models\Question;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UC-023 and DATA-016: a question of one of the five types.
 *
 * - single_choice: 2-10 options, exactly 1 correct;
 * - multiple_choice: 2-10 options, at least 1 correct;
 * - true_false: the 2 options 'true' and 'false', exactly 1 correct;
 * - matching: 2-10 pairs, no empty side;
 * - free_text: no options and no pairs;
 * - points in (0, 100] with at most 2 decimal places.
 *
 * Options and pairs are given in their order (position = index); an id
 * keeps an existing option or pair of the edited question.
 */
class QuestionRequest extends FormRequest
{
    /**
     * OWN-016 before validation, so a refused actor learns nothing from
     * validation errors.
     */
    public function authorize(): bool
    {
        $question = $this->route('question');
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $question instanceof Question
            ? $user->can('update', $question)
            : $user->can('create', Question::class);
    }

    /**
     * @return array<string, array<int, ValidationRule|Closure|array<mixed>|string|object>>
     */
    public function rules(): array
    {
        $type = $this->questionType();

        return [
            'type' => ['required', Rule::enum(QuestionType::class)],
            'prompt' => ['required', 'string', 'max:10000'],
            'points' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'lte:100'],
            ...$this->optionRules($type),
            ...$this->pairRules($type),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'points.decimal' => (string) __('assessment.validation.points_decimals'),
            'points.gt' => (string) __('assessment.validation.points_range'),
            'points.lte' => (string) __('assessment.validation.points_range'),
            'options.required' => (string) __('assessment.validation.options_count'),
            'options.min' => (string) __('assessment.validation.options_count'),
            'options.max' => (string) __('assessment.validation.options_count'),
            'options.size' => (string) __('assessment.validation.true_false_options'),
            'options.prohibited' => (string) __('assessment.validation.no_options'),
            'options.*.text.in' => (string) __('assessment.validation.true_false_options'),
            'options.*.text.distinct' => (string) __('assessment.validation.true_false_options'),
            'pairs.required' => (string) __('assessment.validation.pairs_count'),
            'pairs.min' => (string) __('assessment.validation.pairs_count'),
            'pairs.max' => (string) __('assessment.validation.pairs_count'),
            'pairs.prohibited' => (string) __('assessment.validation.no_pairs'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => (string) __('assessment.attributes.type'),
            'prompt' => (string) __('assessment.attributes.prompt'),
            'points' => (string) __('assessment.attributes.points'),
            'options' => (string) __('assessment.attributes.options'),
            'options.*.text' => (string) __('assessment.attributes.option_text'),
            'pairs' => (string) __('assessment.attributes.pairs'),
            'pairs.*.left_text' => (string) __('assessment.attributes.left_text'),
            'pairs.*.right_text' => (string) __('assessment.attributes.right_text'),
        ];
    }

    /**
     * @return array{type: QuestionType, prompt: string, points: string, options: list<array{id: string|null, text: string, is_correct: bool}>, pairs: list<array{id: string|null, left_text: string, right_text: string}>}
     */
    public function question(): array
    {
        /** @var array<int, array{id?: string|null, text: string, is_correct: mixed}> $options */
        $options = $this->validated('options') ?? [];
        /** @var array<int, array{id?: string|null, left_text: string, right_text: string}> $pairs */
        $pairs = $this->validated('pairs') ?? [];

        return [
            'type' => QuestionType::from((string) $this->validated('type')),
            'prompt' => (string) $this->validated('prompt'),
            'points' => number_format((float) $this->validated('points'), 2, '.', ''),
            'options' => array_values(array_map(fn (array $option): array => [
                'id' => $option['id'] ?? null,
                'text' => (string) $option['text'],
                'is_correct' => filter_var($option['is_correct'], FILTER_VALIDATE_BOOLEAN),
            ], $options)),
            'pairs' => array_values(array_map(fn (array $pair): array => [
                'id' => $pair['id'] ?? null,
                'left_text' => (string) $pair['left_text'],
                'right_text' => (string) $pair['right_text'],
            ], $pairs)),
        ];
    }

    private function questionType(): ?QuestionType
    {
        $type = $this->input('type');

        return is_string($type) ? QuestionType::tryFrom($type) : null;
    }

    /**
     * @return array<string, array<int, Closure|object|string>>
     */
    private function optionRules(?QuestionType $type): array
    {
        if ($type === null || ! $type->hasOptions()) {
            return ['options' => ['prohibited']];
        }

        $count = $type === QuestionType::TrueFalse
            ? ['size:2']
            : ['min:'.QuestionType::MIN_ITEMS, 'max:'.QuestionType::MAX_ITEMS];

        $text = $type === QuestionType::TrueFalse
            ? ['required', 'string', Rule::in(QuestionType::TRUE_FALSE_TEXTS), 'distinct']
            : ['required', 'string', 'max:1000'];

        return [
            'options' => ['required', 'array', 'list', ...$count, $this->correctOptions($type)],
            'options.*' => ['array'],
            'options.*.id' => ['nullable', 'uuid'],
            'options.*.text' => $text,
            'options.*.is_correct' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function pairRules(?QuestionType $type): array
    {
        if ($type === null || ! $type->hasPairs()) {
            return ['pairs' => ['prohibited']];
        }

        return [
            'pairs' => ['required', 'array', 'list', 'min:'.QuestionType::MIN_ITEMS, 'max:'.QuestionType::MAX_ITEMS],
            'pairs.*' => ['array'],
            'pairs.*.id' => ['nullable', 'uuid'],
            'pairs.*.left_text' => ['required', 'string', 'max:1000'],
            'pairs.*.right_text' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Exactly one correct option, or at least one for multiple choice.
     */
    private function correctOptions(QuestionType $type): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($type): void {
            if (! is_array($value)) {
                return;
            }

            $correct = count(array_filter(
                $value,
                fn (mixed $option): bool => is_array($option)
                    && filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ));

            if ($type->hasSingleCorrectOption() && $correct !== 1) {
                $fail((string) __('assessment.validation.exactly_one_correct'));
            } elseif (! $type->hasSingleCorrectOption() && $correct < 1) {
                $fail((string) __('assessment.validation.at_least_one_correct'));
            }
        };
    }
}

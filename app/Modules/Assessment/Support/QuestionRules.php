<?php

namespace App\Modules\Assessment\Support;

use App\Modules\Assessment\Enums\QuestionType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * DATA-016: the validation of one question of one of the five types, for
 * bank questions (Http/Requests/QuestionRequest) and quiz questions
 * (Actions/SaveQuizQuestions) alike.
 *
 * - single_choice: 2-10 options, exactly 1 correct;
 * - multiple_choice: 2-10 options, at least 1 correct;
 * - true_false: the 2 options 'true' and 'false', exactly 1 correct;
 * - matching: 2-10 pairs, no empty side;
 * - free_text: no options and no pairs;
 * - points in (0, 100] with at most 2 decimal places.
 *
 * Options and pairs are given in their order (position = index); an id
 * identifies an existing option or pair.
 */
final class QuestionRules
{
    /**
     * @return array<string, array<int, ValidationRule|Closure|array<mixed>|string|object>>
     */
    public static function rules(mixed $type): array
    {
        $type = is_string($type) ? QuestionType::tryFrom($type) : ($type instanceof QuestionType ? $type : null);

        return [
            'type' => ['required', Rule::enum(QuestionType::class)],
            'prompt' => ['required', 'string', 'max:10000'],
            'points' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'lte:100'],
            ...self::optionRules($type),
            ...self::pairRules($type),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
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
    public static function attributes(): array
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
     * The validated question in normal form: points with 2 decimals, the
     * options and pairs in order, and only the items its type has.
     *
     * @param  array<string, mixed>  $validated
     * @return array{type: QuestionType, prompt: string, points: string, options: list<array{id: string|null, text: string, is_correct: bool}>, pairs: list<array{id: string|null, left_text: string, right_text: string}>}
     */
    public static function normalize(array $validated): array
    {
        $type = QuestionType::from((string) $validated['type']);
        /** @var array<int, array{id?: string|null, text: string, is_correct: mixed}> $options */
        $options = $type->hasOptions() && is_array($validated['options'] ?? null) ? $validated['options'] : [];
        /** @var array<int, array{id?: string|null, left_text: string, right_text: string}> $pairs */
        $pairs = $type->hasPairs() && is_array($validated['pairs'] ?? null) ? $validated['pairs'] : [];

        return [
            'type' => $type,
            'prompt' => (string) $validated['prompt'],
            'points' => self::decimal($validated['points']),
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

    /**
     * A validated number with at most 2 decimals (points, percentages) as an
     * exact decimal string with 2 decimals, e.g. "1.50" (DATA-003).
     */
    public static function decimal(mixed $number): string
    {
        $value = trim(is_scalar($number) ? (string) $number : '');
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '+-'), 2), 2, '');
        $whole = ltrim($whole, '0');

        return ($negative ? '-' : '').($whole === '' ? '0' : $whole).'.'.str_pad(substr($fraction, 0, 2), 2, '0');
    }

    /**
     * @return array<string, array<int, Closure|object|string>>
     */
    private static function optionRules(?QuestionType $type): array
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
            'options' => ['required', 'array', 'list', ...$count, self::correctOptions($type)],
            'options.*' => ['array'],
            'options.*.id' => ['nullable', 'uuid', 'distinct'],
            'options.*.text' => $text,
            'options.*.is_correct' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function pairRules(?QuestionType $type): array
    {
        if ($type === null || ! $type->hasPairs()) {
            return ['pairs' => ['prohibited']];
        }

        return [
            'pairs' => ['required', 'array', 'list', 'min:'.QuestionType::MIN_ITEMS, 'max:'.QuestionType::MAX_ITEMS],
            'pairs.*' => ['array'],
            'pairs.*.id' => ['nullable', 'uuid', 'distinct'],
            'pairs.*.left_text' => ['required', 'string', 'max:1000'],
            'pairs.*.right_text' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Exactly one correct option, or at least one for multiple choice.
     */
    private static function correctOptions(QuestionType $type): Closure
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

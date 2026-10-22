<?php

namespace App\Modules\Assessment\Support;

use App\Modules\Assessment\Enums\QuestionType;
use App\Modules\Assessment\Exceptions\QuestionImportRefused;
use Illuminate\Support\Facades\Validator;

/**
 * DATA-017: turns the data rows of a question CSV (Support/QuestionCsv)
 * into questions and validates each with the DATA-016 rules
 * (Support/QuestionRules).
 *
 * - type is the stored value of the question type;
 * - points accept '.' or ',' as decimal separator;
 * - single and multiple choice: option_n hold the option texts in order
 *   (empty trailing cells ignored); correct lists the 1-based numbers of
 *   the correct options separated by '|';
 * - true_false: no options; correct is 'true' or 'false';
 * - matching: each option_n holds one pair 'left => right', split on the
 *   first ' => '; correct is empty;
 * - free_text: no options; correct is empty.
 */
final class QuestionImportRows
{
    public const string PAIR_SEPARATOR = ' => ';

    public const string CORRECT_SEPARATOR = '|';

    public function __construct(private readonly QuestionCsv $csv) {}

    /**
     * Parses and validates a whole file: either the reason it is refused as a
     * whole, or its rows (see validate()).
     *
     * @return array{refusal: string|null, rows: list<array{line: int, type: string, prompt: string, errors: list<string>, question: array{type: QuestionType, prompt: string, points: string, options: list<array{id: string|null, text: string, is_correct: bool}>, pairs: list<array{id: string|null, left_text: string, right_text: string}>}|null}>}
     */
    public function read(string $contents): array
    {
        try {
            return ['refusal' => null, 'rows' => $this->validate($this->csv->parse($contents))];
        } catch (QuestionImportRefused $refused) {
            return ['refusal' => $refused->getMessage(), 'rows' => []];
        }
    }

    /**
     * Every row with its line number and either its question in normal form
     * (valid) or its translated errors.
     *
     * @param  list<array{line: int, cells: array<string, string>, overflow: bool}>  $rows
     * @return list<array{line: int, type: string, prompt: string, errors: list<string>, question: array{type: QuestionType, prompt: string, points: string, options: list<array{id: string|null, text: string, is_correct: bool}>, pairs: list<array{id: string|null, left_text: string, right_text: string}>}|null}>
     */
    public function validate(array $rows): array
    {
        return array_map(function (array $row): array {
            [$input, $errors] = $this->input($row['cells']);

            if ($row['overflow']) {
                $errors[] = (string) __('assessment.import.errors.extra_cells');
            }

            $rules = QuestionRules::rules($input['type']);

            if ($errors !== []) {
                $rules = array_intersect_key($rules, array_flip(['type', 'prompt', 'points']));
            }

            $validator = Validator::make($input, $rules, QuestionRules::messages(), QuestionRules::attributes());
            $errors = array_values(array_unique([...$errors, ...$validator->errors()->all()]));

            return [
                'line' => $row['line'],
                'type' => (string) $input['type'],
                'prompt' => (string) $input['prompt'],
                'errors' => $errors,
                'question' => $errors === [] ? QuestionRules::normalize($validator->validated()) : null,
            ];
        }, $rows);
    }

    /**
     * The question input of a row, as Support/QuestionRules expects it, and
     * the errors of cells that cannot be turned into it.
     *
     * @param  array<string, string>  $cells
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function input(array $cells): array
    {
        $type = trim($cells['type'] ?? '');
        $correct = trim($cells['correct'] ?? '');
        $items = $this->items($cells);
        $errors = [];

        $input = [
            'type' => $type,
            'prompt' => trim($cells['prompt'] ?? ''),
            'points' => str_replace(',', '.', trim($cells['points'] ?? '')),
        ];

        switch (QuestionType::tryFrom($type)) {
            case QuestionType::SingleChoice:
            case QuestionType::MultipleChoice:
                $numbers = $this->correctNumbers($correct, count($items));

                if ($numbers === null) {
                    $errors[] = (string) __('assessment.import.errors.correct_numbers', ['separator' => self::CORRECT_SEPARATOR]);
                    $numbers = [];
                }

                $input['options'] = array_map(fn (string $text, int $index): array => [
                    'text' => $text,
                    'is_correct' => in_array($index + 1, $numbers, true),
                ], $items, array_keys($items));
                break;

            case QuestionType::TrueFalse:
                $answer = strtolower($correct);

                if ($items !== []) {
                    $errors[] = (string) __('assessment.import.errors.true_false_options');
                }

                if (! in_array($answer, QuestionType::TRUE_FALSE_TEXTS, true)) {
                    $errors[] = (string) __('assessment.import.errors.true_false_correct');
                }

                $input['options'] = array_map(fn (string $text): array => [
                    'text' => $text,
                    'is_correct' => $text === $answer,
                ], QuestionType::TRUE_FALSE_TEXTS);
                break;

            case QuestionType::Matching:
                if ($correct !== '') {
                    $errors[] = (string) __('assessment.import.errors.correct_not_allowed');
                }

                $input['pairs'] = [];

                foreach ($items as $index => $item) {
                    // Padded, so an empty side ("=> right") is found and
                    // then refused as an empty side by DATA-016.
                    $padded = " {$item} ";
                    $separator = strpos($padded, self::PAIR_SEPARATOR);

                    if ($separator === false) {
                        $errors[] = (string) __('assessment.import.errors.pair_format', ['number' => $index + 1, 'separator' => trim(self::PAIR_SEPARATOR)]);

                        continue;
                    }

                    $input['pairs'][] = [
                        'left_text' => trim(substr($padded, 0, $separator)),
                        'right_text' => trim(substr($padded, $separator + strlen(self::PAIR_SEPARATOR))),
                    ];
                }
                break;

            case QuestionType::FreeText:
                if ($items !== []) {
                    $errors[] = (string) __('assessment.validation.no_options');
                }

                if ($correct !== '') {
                    $errors[] = (string) __('assessment.import.errors.correct_not_allowed');
                }
                break;

            case null:
                break;
        }

        return [$input, $errors];
    }

    /**
     * option_1 ... option_10, trimmed, up to the last non-empty one; empty cells in
     * between stay (and fail validation as empty options or pairs).
     *
     * @param  array<string, string>  $cells
     * @return list<string>
     */
    private function items(array $cells): array
    {
        $items = [];

        foreach (range(1, QuestionCsv::MAX_OPTIONS) as $number) {
            $items[] = trim($cells["option_{$number}"] ?? '');
        }

        while ($items !== [] && end($items) === '') {
            array_pop($items);
        }

        return $items;
    }

    /**
     * The 1-based option numbers of the correct cell, or null when one is
     * not the number of a given option or repeats.
     *
     * @return list<int>|null
     */
    private function correctNumbers(string $correct, int $options): ?array
    {
        if ($correct === '') {
            return [];
        }

        $numbers = [];

        foreach (explode(self::CORRECT_SEPARATOR, $correct) as $part) {
            $part = trim($part);

            if (! ctype_digit($part) || (int) $part < 1 || (int) $part > $options || in_array((int) $part, $numbers, true)) {
                return null;
            }

            $numbers[] = (int) $part;
        }

        return $numbers;
    }
}

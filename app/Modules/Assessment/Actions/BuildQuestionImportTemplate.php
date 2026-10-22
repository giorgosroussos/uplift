<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Enums\QuestionType;
use App\Modules\Assessment\Support\QuestionCsv;
use App\Modules\Assessment\Support\QuestionImportRows;

/**
 * UC-030 and DATA-017: the template CSV of the import page, UTF-8 with a
 * BOM (so spreadsheet programs read Greek text correctly), with the header
 * and one valid example row per question type. The example texts follow
 * the UI language.
 */
class BuildQuestionImportTemplate
{
    public const string FILE_NAME = 'question-import-template.csv';

    public function handle(): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, QuestionCsv::headers(), ',', '"', '', "\r\n");

        foreach ($this->examples() as $example) {
            $options = array_pad($example['options'], QuestionCsv::MAX_OPTIONS, '');

            fputcsv($stream, [$example['type']->value, $example['prompt'], $example['points'], $example['correct'], ...$options], ',', '"', '', "\r\n");
        }

        rewind($stream);
        $contents = (string) stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }

    /**
     * @return list<array{type: QuestionType, prompt: string, points: string, correct: string, options: list<string>}>
     */
    private function examples(): array
    {
        $t = fn (string $key): string => (string) __("assessment.import.examples.{$key}");
        $pair = fn (int $n): string => $t("matching_left_{$n}").QuestionImportRows::PAIR_SEPARATOR.$t("matching_right_{$n}");

        return [
            [
                'type' => QuestionType::SingleChoice,
                'prompt' => $t('single_prompt'),
                'points' => '1',
                'correct' => '2',
                'options' => [$t('single_option_1'), $t('single_option_2'), $t('single_option_3')],
            ],
            [
                'type' => QuestionType::MultipleChoice,
                'prompt' => $t('multiple_prompt'),
                'points' => '2.5',
                'correct' => '1|3',
                'options' => [$t('multiple_option_1'), $t('multiple_option_2'), $t('multiple_option_3')],
            ],
            [
                'type' => QuestionType::TrueFalse,
                'prompt' => $t('true_false_prompt'),
                'points' => '1',
                'correct' => 'true',
                'options' => [],
            ],
            [
                'type' => QuestionType::Matching,
                'prompt' => $t('matching_prompt'),
                'points' => '3',
                'correct' => '',
                'options' => [$pair(1), $pair(2), $pair(3)],
            ],
            [
                'type' => QuestionType::FreeText,
                'prompt' => $t('free_text_prompt'),
                'points' => '5',
                'correct' => '',
                'options' => [],
            ],
        ];
    }
}

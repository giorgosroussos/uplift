<?php

namespace App\Modules\Assessment\Support;

use App\Modules\Assessment\Exceptions\QuestionImportRefused;

/**
 * DATA-017: reads a question CSV into its data rows.
 *
 * - UTF-8 (an optional BOM is ignored); any other encoding refuses the file;
 * - a required header row of the fixed names of headers(), in any order;
 *   a missing, unknown or repeated column refuses the file; columns with
 *   an empty header are ignored but must stay empty;
 * - delimiter ',' or ';', detected from the header row;
 * - at most MAX_ROWS data rows and MAX_BYTES bytes;
 * - blank lines are ignored; a row's line number is the file line it
 *   starts on (the header is line 1).
 *
 * The cells are returned as they are; Support/QuestionImportRows turns
 * them into questions and validates them.
 */
final class QuestionCsv
{
    public const int MAX_ROWS = 500;

    public const int MAX_BYTES = 1024 * 1024;

    public const int MAX_OPTIONS = 10;

    private const string BOM = "\xEF\xBB\xBF";

    /**
     * The fixed header names, in the order of the template.
     *
     * @return list<string>
     */
    public static function headers(): array
    {
        $options = array_map(fn (int $n): string => "option_{$n}", range(1, self::MAX_OPTIONS));

        return ['type', 'prompt', 'points', 'correct', ...$options];
    }

    /**
     * @return list<array{line: int, cells: array<string, string>, overflow: bool}>
     *
     * @throws QuestionImportRefused
     */
    public function parse(string $contents): array
    {
        if (strlen($contents) > self::MAX_BYTES) {
            throw new QuestionImportRefused((string) __('assessment.import.refused.too_large'));
        }

        if (str_starts_with($contents, self::BOM)) {
            $contents = substr($contents, strlen(self::BOM));
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            throw new QuestionImportRefused((string) __('assessment.import.refused.not_utf8'));
        }

        if (trim($contents) === '') {
            throw new QuestionImportRefused((string) __('assessment.import.refused.empty'));
        }

        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new QuestionImportRefused((string) __('assessment.import.refused.unreadable'));
        }

        try {
            fwrite($stream, $contents);
            rewind($stream);

            return $this->records($stream, $contents, $this->delimiter($contents));
        } finally {
            fclose($stream);
        }
    }

    /**
     * ';' when the header row has more semicolons than commas, else ','.
     */
    private function delimiter(string $contents): string
    {
        $end = strpos($contents, "\n");
        $header = $end === false ? $contents : substr($contents, 0, $end);

        return substr_count($header, ';') > substr_count($header, ',') ? ';' : ',';
    }

    /**
     * @param  resource  $stream
     * @return list<array{line: int, cells: array<string, string>, overflow: bool}>
     */
    private function records($stream, string $contents, string $delimiter): array
    {
        $columns = null;
        $rows = [];
        $line = 1;

        while (true) {
            $start = (int) ftell($stream);
            $record = fgetcsv($stream, null, $delimiter, '"', '');

            if ($record === false) {
                break;
            }

            $recordLine = $line;
            $line += substr_count(substr($contents, $start, (int) ftell($stream) - $start), "\n");

            $cells = array_map(fn (?string $cell): string => (string) $cell, $record);

            if (implode('', array_map(trim(...), $cells)) === '') {
                continue;
            }

            if ($columns === null) {
                $columns = $this->columns($cells);

                continue;
            }

            if (count($rows) === self::MAX_ROWS) {
                throw new QuestionImportRefused((string) __('assessment.import.refused.too_many_rows', ['max' => self::MAX_ROWS]));
            }

            $row = [];
            $ignored = array_slice($cells, count($columns));

            foreach ($columns as $index => $name) {
                if ($name === '') {
                    $ignored[] = $cells[$index] ?? '';
                } else {
                    $row[$name] = $cells[$index] ?? '';
                }
            }

            $rows[] = [
                'line' => $recordLine,
                'cells' => $row,
                'overflow' => implode('', array_map(trim(...), $ignored)) !== '',
            ];
        }

        return $rows;
    }

    /**
     * The column names of the header row, by position.
     *
     * @param  list<string>  $cells
     * @return list<string>
     */
    private function columns(array $cells): array
    {
        $columns = array_map(fn (string $cell): string => strtolower(trim($cell)), $cells);
        $known = self::headers();

        $unknown = [];

        foreach (array_count_values(array_filter($columns, fn (string $name): bool => $name !== '')) as $name => $count) {
            if (! in_array((string) $name, $known, true) || $count > 1) {
                $unknown[] = (string) $name;
            }
        }

        if ($unknown !== []) {
            throw new QuestionImportRefused((string) __('assessment.import.refused.unknown_headers', ['columns' => implode(', ', $unknown)]));
        }

        $missing = array_values(array_diff($known, $columns));

        if ($missing !== []) {
            throw new QuestionImportRefused((string) __('assessment.import.refused.missing_headers', ['columns' => implode(', ', $missing)]));
        }

        return $columns;
    }
}

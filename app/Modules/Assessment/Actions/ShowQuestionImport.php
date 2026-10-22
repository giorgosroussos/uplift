<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Support\QuestionImportFiles;
use App\Modules\Assessment\Support\QuestionImportRows;
use App\Modules\Identity\Models\User;

/**
 * UC-030 and DATA-017: the report of the uploader's pending import, every
 * row with its line number and 'valid' or its errors. Another user's
 * import is not found.
 */
class ShowQuestionImport
{
    public function __construct(
        private readonly QuestionImportFiles $files,
        private readonly QuestionImportRows $rows,
    ) {}

    /**
     * @return array{id: string, refusal: string|null, valid_count: int, invalid_count: int, rows: list<array{line: int, type: string, prompt: string, valid: bool, errors: list<string>}>}
     */
    public function handle(User $uploader, string $id): array
    {
        $contents = $this->files->get($uploader, $id);

        abort_if($contents === null, 404);

        $report = $this->rows->read($contents);
        $valid = count(array_filter($report['rows'], fn (array $row): bool => $row['question'] !== null));

        return [
            'id' => $id,
            'refusal' => $report['refusal'],
            'valid_count' => $valid,
            'invalid_count' => count($report['rows']) - $valid,
            'rows' => array_map(fn (array $row): array => [
                'line' => $row['line'],
                'type' => $row['type'],
                'prompt' => $row['prompt'],
                'valid' => $row['question'] !== null,
                'errors' => $row['errors'],
            ], $report['rows']),
        ];
    }
}

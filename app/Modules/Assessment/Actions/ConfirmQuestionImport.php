<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Support\QuestionImportFiles;
use App\Modules\Assessment\Support\QuestionImportRows;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-030 and DATA-017: confirms the uploader's pending import. The stored
 * file is parsed and validated again; its valid rows become questions of
 * the uploader's own bank in file order, in one transaction, and the
 * invalid rows are skipped. The file is then deleted. Without a valid row
 * the confirm is refused and nothing changes. Another user's import is not
 * found.
 */
class ConfirmQuestionImport
{
    public function __construct(
        private readonly QuestionImportFiles $files,
        private readonly QuestionImportRows $rows,
        private readonly SaveBankQuestion $save,
    ) {}

    /**
     * @return array{imported: int, skipped: int}
     *
     * @throws ValidationException
     */
    public function handle(User $uploader, string $id): array
    {
        $contents = $this->files->get($uploader, $id);

        abort_if($contents === null, 404);

        $report = $this->rows->read($contents);
        $questions = array_values(array_filter(array_column($report['rows'], 'question')));

        if ($questions === []) {
            throw ValidationException::withMessages([
                'import' => $report['refusal'] ?? (string) __('assessment.import.errors.no_valid_rows'),
            ]);
        }

        DB::transaction(function () use ($uploader, $questions): void {
            foreach ($questions as $question) {
                $this->save->handle($uploader, null, $question);
            }
        });

        $this->files->delete($uploader, $id);

        return ['imported' => count($questions), 'skipped' => count($report['rows']) - count($questions)];
    }
}

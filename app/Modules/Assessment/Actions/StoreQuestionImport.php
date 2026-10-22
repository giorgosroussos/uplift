<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Exceptions\QuestionImportRefused;
use App\Modules\Assessment\Support\QuestionCsv;
use App\Modules\Assessment\Support\QuestionImportFiles;
use App\Modules\Identity\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * UC-030 and DATA-017: stores an uploaded question CSV for its report and
 * returns the import id. A file refused as a whole (not UTF-8, wrong
 * headers, too many rows, too large) is not stored; the reason is a
 * validation error of the file field.
 */
class StoreQuestionImport
{
    public function __construct(
        private readonly QuestionCsv $csv,
        private readonly QuestionImportFiles $files,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $uploader, UploadedFile $file): string
    {
        $contents = (string) $file->get();

        try {
            $this->csv->parse($contents);
        } catch (QuestionImportRefused $refused) {
            throw ValidationException::withMessages(['file' => $refused->getMessage()]);
        }

        return $this->files->put($uploader, $contents);
    }
}

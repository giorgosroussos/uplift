<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Support\QuestionImportFiles;
use App\Modules\Identity\Models\User;

/**
 * UC-030 and DATA-017: cancels the uploader's pending import; nothing is
 * imported and the file is deleted. Another user's import is not found.
 */
class CancelQuestionImport
{
    public function __construct(private readonly QuestionImportFiles $files) {}

    public function handle(User $uploader, string $id): void
    {
        abort_if($this->files->get($uploader, $id) === null, 404);

        $this->files->delete($uploader, $id);
    }
}

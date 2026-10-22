<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Support\QuestionImportFiles;
use Illuminate\Support\Facades\Date;

/**
 * DATA-017: deletes leftover question import files (neither confirmed nor
 * cancelled) older than 24 hours. Run daily by the scheduler.
 */
class PruneQuestionImports
{
    public const int MAX_AGE_HOURS = 24;

    public function __construct(private readonly QuestionImportFiles $files) {}

    /**
     * @return int the number of deleted files
     */
    public function handle(): int
    {
        $disk = $this->files->disk();
        $cutoff = Date::now()->subHours(self::MAX_AGE_HOURS)->getTimestamp();
        $deleted = 0;

        foreach ($disk->allFiles(QuestionImportFiles::DIRECTORY) as $path) {
            if ($disk->lastModified($path) < $cutoff && $disk->delete($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}

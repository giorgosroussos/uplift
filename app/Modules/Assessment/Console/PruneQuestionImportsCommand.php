<?php

namespace App\Modules\Assessment\Console;

use App\Modules\Assessment\Actions\PruneQuestionImports;
use Illuminate\Console\Command;

/**
 * DATA-017: deletes leftover question import files older than 24 hours.
 * Scheduled daily by AssessmentServiceProvider.
 */
class PruneQuestionImportsCommand extends Command
{
    protected $signature = 'assessment:prune-question-imports';

    protected $description = 'Delete question import files older than 24 hours that were neither confirmed nor cancelled';

    public function handle(PruneQuestionImports $prune): int
    {
        $count = $prune->handle();

        $this->info("Deleted {$count} question import file(s).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Modules\Assessment\Console;

use App\Modules\Assessment\Actions\ReleaseVisibleResults;
use Illuminate\Console\Command;

/**
 * DEC-003: releases graded quiz attempts whose score has become visible.
 * Scheduled every minute by AssessmentServiceProvider.
 */
class ReleaseResultsCommand extends Command
{
    protected $signature = 'assessment:release-results';

    protected $description = 'Release graded quiz attempts whose score has become visible to the student';

    public function handle(ReleaseVisibleResults $release): int
    {
        $count = $release->handle();

        $this->info("Released {$count} attempt(s).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Modules\Assessment\Console;

use App\Modules\Assessment\Actions\SubmitDueAttempts;
use Illuminate\Console\Command;

/**
 * SCOPE-024: auto-submits quiz attempts whose time is up. Scheduled every
 * minute by AssessmentServiceProvider.
 */
class SubmitDueAttemptsCommand extends Command
{
    protected $signature = 'assessment:submit-due-attempts';

    protected $description = 'Submit in-progress quiz attempts whose time limit or deadline has passed';

    public function handle(SubmitDueAttempts $submit): int
    {
        $count = $submit->handle();

        $this->info("Submitted {$count} attempt(s).");

        return self::SUCCESS;
    }
}

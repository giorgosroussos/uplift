<?php

namespace App\Modules\Live\Console;

use App\Modules\Live\Actions\FinishDueLiveSessions;
use App\Modules\Live\Actions\StartScheduledLiveSessions;
use Illuminate\Console\Command;

/**
 * SCOPE-021: starts scheduled live quizzes whose start time has come and
 * finishes running sessions whose end time has passed, without teacher
 * action. Scheduled every five seconds by LiveServiceProvider.
 */
class AdvanceLiveSessionsCommand extends Command
{
    protected $signature = 'live:advance';

    protected $description = 'Start scheduled live quizzes that are due and finish live sessions whose end time has passed';

    public function handle(StartScheduledLiveSessions $start, FinishDueLiveSessions $finish): int
    {
        $started = $start->handle();
        $finished = $finish->handle();

        $this->info("Started {$started} and finished {$finished} live session(s).");

        return self::SUCCESS;
    }
}

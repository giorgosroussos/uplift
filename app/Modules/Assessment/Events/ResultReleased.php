<?php

namespace App\Modules\Assessment\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * DEC-003: the final score of a quiz attempt became visible to its student
 * and the attempt was released (result_released_at set). Dispatched at most
 * once per attempt, after commit, e.g. for Notifications, which tells the
 * student. Carries ids only, never points (SEC-003).
 */
final class ResultReleased implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $attemptId,
    ) {}
}

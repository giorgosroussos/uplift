<?php

namespace App\Modules\Live\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A live session moved from its lobby to running; its common end time is
 * fixed (SCOPE-021). Dispatched after the change has committed; carries
 * ids only.
 */
final class LiveSessionStarted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $sessionId,
        public readonly string $quizId,
    ) {}
}

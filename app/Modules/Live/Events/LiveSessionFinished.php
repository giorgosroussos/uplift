<?php

namespace App\Modules\Live\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A live session finished (running -> finished): every participation is
 * submitted and scored and the final ranks are set. Gamification awards
 * XP from it (PHASE-6, SCOPE-018); no grade goes to My results. Dispatched
 * after the change has committed; carries ids only, never scores (SEC-003).
 */
final class LiveSessionFinished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $sessionId,
        public readonly string $quizId,
    ) {}
}

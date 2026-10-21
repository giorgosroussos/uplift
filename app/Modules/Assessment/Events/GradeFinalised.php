<?php

namespace App\Modules\Assessment\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The score of a quiz attempt became final (SCOPE-024: submitted ->
 * graded): on submission when nothing waits for grading, or when the
 * teacher grades its last pending free-text answer (UC-026 step 3), e.g.
 * for Notifications, which tells the student. Dispatched after the change
 * has committed; carries ids only, never points (SEC-003).
 */
final class GradeFinalised implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $attemptId,
    ) {}
}

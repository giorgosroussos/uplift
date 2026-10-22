<?php

namespace App\Modules\Assessment\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * DEC-003: a regrade changed earned_points of an already released quiz
 * attempt. Dispatched after commit, e.g. for Notifications, which tells
 * the student. Carries ids only, never points (SEC-003).
 */
final class ResultChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $attemptId,
    ) {}
}

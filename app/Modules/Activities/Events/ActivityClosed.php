<?php

namespace App\Modules\Activities\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A teacher closed a published activity, e.g. for the Assessment module,
 * which submits the quiz's in-progress attempts (SCOPE-024). Dispatched
 * after the status change has committed; carries ids only.
 */
final class ActivityClosed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $activityId,
    ) {}
}

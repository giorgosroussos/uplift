<?php

namespace App\Modules\Activities\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The owning teacher approved or rejected a topic proposal (UC-027), e.g.
 * for the Notifications module, which notifies the student or the group's
 * members (TopicProposalDecided, INT-001). Dispatched after the decision
 * has committed; carries ids and the new status only.
 */
final class TopicProposalDecided implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $topicProposalId,
        public readonly string $status,
    ) {}
}

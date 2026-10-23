<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Activities\Events\TopicProposalDecided;
use App\Modules\Notifications\Actions\NotifyTopicProposalDecided;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\TenantContext;

/**
 * Notifies the student or group whose topic proposal was approved or
 * rejected (DEC-001, UC-027). Runs synchronously after the decision has
 * committed, so the in-app notifications exist at once; the emails are
 * queued.
 */
class SendTopicProposalNotification
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly NotifyTopicProposalDecided $notify,
    ) {}

    public function handle(TopicProposalDecided $event): void
    {
        $organization = Organization::query()->find($event->organizationId);

        if ($organization === null) {
            return;
        }

        $this->tenant->run($organization, fn () => $this->notify->handle($event->topicProposalId));
    }
}

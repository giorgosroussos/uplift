<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Activities\Events\ActivityPublished;
use App\Modules\Notifications\Actions\NotifyActivityPublished;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\TenantContext;

/**
 * Notifies the enrolled students of a newly published activity (DEC-001).
 * Runs synchronously after the publish has committed, so the in-app
 * notifications exist at once; the emails are queued.
 */
class SendActivityNotifications
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly NotifyActivityPublished $notify,
    ) {}

    public function handle(ActivityPublished $event): void
    {
        $organization = Organization::query()->find($event->organizationId);

        if ($organization === null) {
            return;
        }

        $this->tenant->run($organization, fn () => $this->notify->handle($event->activityId, $event->actorId));
    }
}

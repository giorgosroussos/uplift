<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Courses\Events\AnnouncementPublished;
use App\Modules\Notifications\Actions\NotifyAnnouncementPublished;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\TenantContext;

/**
 * Notifies the enrolled students of a newly published announcement
 * (DEC-002). Runs synchronously after the post has committed, so the in-app
 * notifications exist at once; the emails are queued.
 */
class SendAnnouncementNotifications
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly NotifyAnnouncementPublished $notify,
    ) {}

    public function handle(AnnouncementPublished $event): void
    {
        $organization = Organization::query()->find($event->organizationId);

        if ($organization === null) {
            return;
        }

        $this->tenant->run($organization, fn () => $this->notify->handle($event->announcementId, $event->authorId));
    }
}

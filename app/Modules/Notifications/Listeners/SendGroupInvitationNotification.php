<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Courses\Events\GroupInvitationCreated;
use App\Modules\Notifications\Actions\NotifyGroupInvitation;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\TenantContext;

/**
 * Notifies a student invited to a group (DEC-001, SCOPE-025). Runs
 * synchronously after the invitation has committed, so the in-app
 * notification exists at once; the email is queued.
 */
class SendGroupInvitationNotification
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly NotifyGroupInvitation $notify,
    ) {}

    public function handle(GroupInvitationCreated $event): void
    {
        $organization = Organization::query()->find($event->organizationId);

        if ($organization === null) {
            return;
        }

        $this->tenant->run($organization, fn () => $this->notify->handle($event->groupMembershipId));
    }
}

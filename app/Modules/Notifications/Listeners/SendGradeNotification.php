<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Assessment\Events\GradeFinalised;
use App\Modules\Notifications\Actions\NotifyGradeFinalised;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\TenantContext;

/**
 * Tells the student that their attempt got its final grade (DEC-001). Runs
 * synchronously after the grade has committed, so the in-app notification
 * exists at once; the email is queued.
 */
class SendGradeNotification
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly NotifyGradeFinalised $notify,
    ) {}

    public function handle(GradeFinalised $event): void
    {
        $organization = Organization::query()->find($event->organizationId);

        if ($organization === null) {
            return;
        }

        $this->tenant->run($organization, fn () => $this->notify->handle($event->attemptId));
    }
}

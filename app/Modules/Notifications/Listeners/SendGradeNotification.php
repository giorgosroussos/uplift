<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Assessment\Events\ResultChanged;
use App\Modules\Assessment\Events\ResultReleased;
use App\Modules\Notifications\Actions\NotifyGrade;
use App\Modules\Notifications\Enums\NotificationKind;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\TenantContext;

/**
 * DEC-003: tells the student about their grade when Assessment releases
 * the attempt's result (grade_finalised) or a regrade changes a released
 * result (grade_changed). Runs synchronously after the change has
 * committed, so the in-app notification exists at once; the email is
 * queued.
 */
class SendGradeNotification
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly NotifyGrade $notify,
    ) {}

    public function handle(ResultReleased|ResultChanged $event): void
    {
        $organization = Organization::query()->find($event->organizationId);

        if ($organization === null) {
            return;
        }

        $kind = $event instanceof ResultChanged ? NotificationKind::GradeChanged : NotificationKind::GradeFinalised;

        $this->tenant->run($organization, fn () => $this->notify->handle($event->attemptId, $kind));
    }
}

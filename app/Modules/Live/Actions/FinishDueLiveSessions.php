<?php

namespace App\Modules\Live\Actions;

use App\Modules\Live\Enums\LiveSessionStatus;
use App\Modules\Live\Models\LiveSession;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\OrganizationScope;
use App\Modules\Tenancy\Support\TenantContext;
use Carbon\CarbonImmutable;

/**
 * SCOPE-021: finishes and scores every running session whose common end
 * time has passed, in every organisation, without teacher action, even if
 * the host has disconnected. Run by the scheduler as a safety net behind
 * the finish queued at the start (FinishLiveSessionJob); each session is
 * finished as its own tenant.
 */
class FinishDueLiveSessions
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly FinishLiveSession $finish,
    ) {}

    /**
     * @return int the number of sessions finished
     */
    public function handle(): int
    {
        $due = LiveSession::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('status', LiveSessionStatus::Running->value)
            ->where('ends_at', '<=', CarbonImmutable::now())
            ->get(['id', 'organization_id'])
            ->groupBy('organization_id');

        $count = 0;

        foreach ($due as $organizationId => $sessions) {
            $organization = Organization::query()->find($organizationId);

            if ($organization === null) {
                continue;
            }

            $count += $this->tenant->run($organization, function () use ($sessions): int {
                $finished = 0;

                foreach ($sessions as $session) {
                    $fresh = LiveSession::query()->find($session->id);

                    if ($fresh !== null && $this->finish->handle($fresh)) {
                        $finished++;
                    }
                }

                return $finished;
            });
        }

        return $count;
    }
}

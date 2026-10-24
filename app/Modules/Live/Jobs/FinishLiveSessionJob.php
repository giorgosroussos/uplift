<?php

namespace App\Modules\Live\Jobs;

use App\Modules\Live\Actions\FinishLiveSession;
use App\Modules\Live\Models\LiveSession;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Finishes a live session at its common end time (SCOPE-021); queued with
 * a delay when the session starts. Carries ids only. A session that is
 * not due yet or already finished is left as it is, so running early,
 * late or twice is harmless; FinishDueLiveSessions is the safety net.
 */
final class FinishLiveSessionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $sessionId,
    ) {}

    public function handle(TenantContext $tenant, FinishLiveSession $finish): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        $tenant->run($organization, function () use ($finish): void {
            $session = LiveSession::query()->find($this->sessionId);

            if ($session !== null) {
                $finish->handle($session);
            }
        });
    }
}

<?php

namespace App\Modules\Live\Actions;

use App\Modules\Live\Broadcasting\LiveChannels;
use App\Modules\Live\Models\LiveSession;

/**
 * The host teacher's state snapshot of a session (UC-024, FLOW-002): the
 * channels to subscribe to, the live progress (ShowLiveProgress) and,
 * once finished, the results (ShowLiveResults). Fetched on load, after a
 * reconnection and every 2 seconds while the websocket is down (ARCH-006).
 * Read straight from PostgreSQL. The caller authorises
 * (LiveSessionPolicy::host).
 */
class ShowLiveHostState
{
    public function __construct(
        private readonly ShowLiveProgress $progress,
        private readonly ShowLiveResults $results,
    ) {}

    /**
     * @return array{channels: array{session: string, host: string}, progress: array<string, mixed>, results: array<string, mixed>|null}
     */
    public function handle(LiveSession $session): array
    {
        return [
            'channels' => [
                'session' => LiveChannels::session($session->organization_id, $session->id),
                'host' => LiveChannels::host($session->organization_id, $session->id),
            ],
            'progress' => $this->progress->handle($session),
            'results' => $this->results->handle($session),
        ];
    }
}

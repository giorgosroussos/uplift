<?php

namespace App\Modules\Live\Actions;

use App\Modules\Live\Broadcasting\LiveChannels;
use App\Modules\Live\Models\LiveParticipant;
use App\Modules\Live\Models\LiveSession;

/**
 * A student's state snapshot of their participation (UC-025, FLOW-002):
 * the channel to subscribe to, the session's status, ends_at and
 * server_now, their own saved answers and whether they submitted
 * (ShowLiveParticipation) and, once finished, the results
 * (ShowLiveResults: own score, ranking and podium only when enabled).
 * Nothing tells correctness before the finish (SCOPE-021). Fetched on
 * load, after a reconnection and every 2 seconds while the websocket is
 * down (ARCH-006). The caller authorises (LiveParticipantPolicy::play).
 */
class ShowLiveParticipantState
{
    public function __construct(
        private readonly ShowLiveParticipation $participation,
        private readonly ShowLiveResults $results,
    ) {}

    /**
     * @return array{channels: array{session: string}, participation: array<string, mixed>, results: array<string, mixed>|null}
     */
    public function handle(LiveParticipant $participant): array
    {
        /** @var LiveSession $session */
        $session = LiveSession::query()->findOrFail($participant->live_session_id);

        return [
            'channels' => [
                'session' => LiveChannels::session($session->organization_id, $session->id),
            ],
            'participation' => $this->participation->handle($participant),
            'results' => $this->results->handle($session, $participant),
        ];
    }
}

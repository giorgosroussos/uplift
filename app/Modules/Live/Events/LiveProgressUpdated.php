<?php

namespace App\Modules\Live\Events;

use App\Modules\Live\Actions\ShowLiveProgress;
use App\Modules\Live\Broadcasting\LiveChannels;
use App\Modules\Live\Models\LiveParticipant;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * UC-024 step 2 and 4: a participant joined, answered a question or
 * submitted. Broadcast to the host's private channel only (OWN-012,
 * OWN-013) as the participant's progress row of ShowLiveProgress: the
 * student's nickname and avatar (SEC-010), how many questions they
 * answered and whether they submitted, never an answer or its
 * correctness. Queued after commit; plain values only.
 *
 * @phpstan-import-type ProgressRow from ShowLiveProgress
 */
final class LiveProgressUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  ProgressRow  $participant
     */
    public function __construct(
        public readonly string $organizationId,
        public readonly string $sessionId,
        public readonly array $participant,
    ) {}

    public static function of(LiveParticipant $participant, int $answered): self
    {
        return new self(
            $participant->organization_id,
            $participant->live_session_id,
            ShowLiveProgress::row($participant, $answered),
        );
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(LiveChannels::host($this->organizationId, $this->sessionId))];
    }

    public function broadcastAs(): string
    {
        return 'live.progress.updated';
    }

    /**
     * @return array{session_id: string, participant: ProgressRow}
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'participant' => $this->participant,
        ];
    }
}

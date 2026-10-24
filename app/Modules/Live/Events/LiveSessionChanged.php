<?php

namespace App\Modules\Live\Events;

use App\Modules\Live\Broadcasting\LiveChannels;
use App\Modules\Live\Models\LiveSession;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast to the session's presence channel when its status changes
 * (started, cancelled, finished): the status, the common end time and
 * the server's clock at sending, so clients derive countdowns from the
 * server (ARCH-006). Carries no score, answer or correctness: on
 * "finished" each client fetches its authorised state snapshot
 * (SCOPE-021, SEC-010). Queued after commit; plain values only, so the
 * queue worker needs no tenant.
 */
final class LiveSessionChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $sessionId,
        public readonly string $status,
        public readonly ?string $startedAt,
        public readonly ?string $endsAt,
    ) {}

    public static function of(LiveSession $session): self
    {
        return new self(
            $session->organization_id,
            $session->id,
            $session->status->value,
            $session->started_at?->toIso8601String(),
            $session->ends_at?->toIso8601String(),
        );
    }

    /**
     * @return list<PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel(LiveChannels::session($this->organizationId, $this->sessionId))];
    }

    public function broadcastAs(): string
    {
        return 'live.session.changed';
    }

    /**
     * @return array{session: array{id: string, status: string, started_at: string|null, ends_at: string|null, server_now: string}}
     */
    public function broadcastWith(): array
    {
        return [
            'session' => [
                'id' => $this->sessionId,
                'status' => $this->status,
                'started_at' => $this->startedAt,
                'ends_at' => $this->endsAt,
                'server_now' => CarbonImmutable::now()->toIso8601String(),
            ],
        ];
    }
}

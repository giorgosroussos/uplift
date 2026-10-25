<?php

namespace App\Modules\Live\Broadcasting;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Presence channel of the host teacher of a live session
 * (LiveChannels::HOST): only the host (LiveSessionPolicy::host) of the
 * channel's organisation is let in, known by nickname and avatar only,
 * and receives participants' progress: who joined, answered counts, who
 * submitted (FLOW-001, OWN-012, OWN-013). No student is ever a member.
 */
final class LiveHostChannel
{
    /**
     * @return array{id: string, nickname: string, avatar: string|null}|false
     */
    public function join(User $user, string $organizationId, string $sessionId): array|false
    {
        $session = LiveSessionChannel::session($user, $organizationId, $sessionId);

        return $session !== null && Gate::forUser($user)->allows('host', $session)
            ? $user->publicProfile()
            : false;
    }
}

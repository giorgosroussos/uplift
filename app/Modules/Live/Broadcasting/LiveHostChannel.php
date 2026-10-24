<?php

namespace App\Modules\Live\Broadcasting;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Private channel of the host teacher of a live session
 * (LiveChannels::HOST): only the host (LiveSessionPolicy::host) of the
 * channel's organisation receives participants' progress (OWN-012,
 * OWN-013).
 */
final class LiveHostChannel
{
    public function join(User $user, string $organizationId, string $sessionId): bool
    {
        $session = LiveSessionChannel::session($user, $organizationId, $sessionId);

        return $session !== null && Gate::forUser($user)->allows('host', $session);
    }
}

<?php

namespace App\Modules\Live\Broadcasting;

use App\Modules\Identity\Models\User;
use App\Modules\Live\Actions\ShowLiveResults;
use App\Modules\Live\Models\LiveSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Private channel of a live session (LiveChannels::SESSION): the host
 * teacher (LiveSessionPolicy::host) and the students who joined it
 * (LiveParticipantPolicy::play on their own participation). It has no
 * member list: students never see who else joined (UC-025, OWN-012).
 * Subscribing to it as a presence channel is refused. Anyone else, and
 * anyone of another organisation, is refused.
 */
final class LiveSessionChannel
{
    public function join(User $user, string $organizationId, string $sessionId): bool
    {
        if (str_starts_with((string) request()->input('channel_name'), 'presence-')) {
            return false;
        }

        $session = self::session($user, $organizationId, $sessionId);

        if ($session === null) {
            return false;
        }

        if (Gate::forUser($user)->allows('host', $session)) {
            return true;
        }

        $participant = ShowLiveResults::participationOf($session, $user);

        return $participant !== null && Gate::forUser($user)->allows('play', $participant);
    }

    /**
     * The session named by the channel, only when the channel's
     * organisation is the user's and the session belongs to it.
     */
    public static function session(User $user, string $organizationId, string $sessionId): ?LiveSession
    {
        if (! $user->canAct() || $user->organization_id !== $organizationId || ! Str::isUuid($sessionId)) {
            return null;
        }

        $session = LiveSession::query()->find($sessionId);

        return $session !== null && $session->organization_id === $organizationId ? $session : null;
    }
}

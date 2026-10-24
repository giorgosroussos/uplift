<?php

namespace App\Modules\Live\Broadcasting;

use App\Modules\Identity\Models\User;
use App\Modules\Live\Actions\ShowLiveResults;
use App\Modules\Live\Models\LiveSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Presence channel of a live session (LiveChannels::SESSION): the host
 * teacher (LiveSessionPolicy::host) and the students who joined it
 * (LiveParticipantPolicy::play on their own participation). Members are
 * known by nickname and avatar only (SEC-008, SEC-010). Anyone else, and
 * anyone of another organisation, is refused.
 */
final class LiveSessionChannel
{
    /**
     * @return array{id: string, nickname: string, avatar: string|null}|false
     */
    public function join(User $user, string $organizationId, string $sessionId): array|false
    {
        $session = self::session($user, $organizationId, $sessionId);

        if ($session === null) {
            return false;
        }

        if (Gate::forUser($user)->allows('host', $session)) {
            return $user->publicProfile();
        }

        $participant = ShowLiveResults::participationOf($session, $user);

        if ($participant !== null && Gate::forUser($user)->allows('play', $participant)) {
            return $user->publicProfile();
        }

        return false;
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

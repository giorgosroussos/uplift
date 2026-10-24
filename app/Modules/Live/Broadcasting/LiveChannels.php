<?php

namespace App\Modules\Live\Broadcasting;

/**
 * Names of the live quiz broadcast channels (SEC-010: private or presence
 * only, with the organisation id in the name, authorised by the Policies):
 *
 * - session (presence): the host and the session's participants; members
 *   are known by nickname and avatar only. Carries status changes
 *   (started, cancelled, finished) with ends_at and server_now.
 * - host (private): the host teacher only. Carries each participant's
 *   progress (joined, answered count, submitted), never an answer.
 *
 * Names are without the "presence-" / "private-" prefix, as Echo's join()
 * and private() and Laravel's channel routes expect them.
 */
final class LiveChannels
{
    public const string SESSION = 'organization.{organizationId}.live-session.{sessionId}';

    public const string HOST = 'organization.{organizationId}.live-session.{sessionId}.host';

    public static function session(string $organizationId, string $sessionId): string
    {
        return "organization.{$organizationId}.live-session.{$sessionId}";
    }

    public static function host(string $organizationId, string $sessionId): string
    {
        return self::session($organizationId, $sessionId).'.host';
    }
}

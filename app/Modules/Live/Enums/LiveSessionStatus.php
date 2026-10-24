<?php

namespace App\Modules\Live\Enums;

/**
 * State model of ENT-LiveSession: initial `lobby`; transitions
 * `lobby -> running`, `lobby -> cancelled` and `running -> finished`.
 * A cancelled lobby means the quiz was not run (UC-024), so a new lobby
 * may be opened; a running or finished session means the quiz was run
 * and cannot run again (SCOPE-019).
 */
enum LiveSessionStatus: string
{
    case Lobby = 'lobby';
    case Cancelled = 'cancelled';
    case Running = 'running';
    case Finished = 'finished';

    public static function initial(): self
    {
        return self::Lobby;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Lobby => [self::Running, self::Cancelled],
            self::Running => [self::Finished],
            self::Cancelled, self::Finished => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * Whether the session counts as a run of the quiz (started).
     */
    public function hasStarted(): bool
    {
        return $this === self::Running || $this === self::Finished;
    }

    /**
     * The statuses of sessions that have started.
     *
     * @return list<string>
     */
    public static function started(): array
    {
        return [self::Running->value, self::Finished->value];
    }
}

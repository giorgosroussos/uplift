<?php

namespace App\Modules\Identity\Enums;

/**
 * State model of ENT-Invitation: initial `pending`; transitions
 * `pending -> accepted`, `pending -> revoked`, `pending -> expired` and
 * `expired -> pending` (resend, DATA-015).
 */
enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public static function initial(): self
    {
        return self::Pending;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Accepted, self::Revoked, self::Expired],
            self::Expired => [self::Pending],
            self::Accepted, self::Revoked => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}

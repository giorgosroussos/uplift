<?php

namespace App\Modules\Courses\Enums;

/**
 * State model of ENT-GroupMembership: initial `invited`; transitions
 * `invited -> member`, `invited -> declined`, `invited -> withdrawn` and
 * `member -> left`. The one row created as `member` is the creator's own
 * membership (SCOPE-025).
 */
enum GroupMembershipStatus: string
{
    case Invited = 'invited';
    case Member = 'member';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Left = 'left';

    public static function initial(): self
    {
        return self::Invited;
    }

    /**
     * The states that count against the one-open-row-per-student rule.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Invited, self::Member];
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Invited => [self::Member, self::Declined, self::Withdrawn],
            self::Member => [self::Left],
            self::Declined, self::Withdrawn, self::Left => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}

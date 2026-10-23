<?php

namespace App\Modules\Activities\Enums;

/**
 * State model of ENT-TopicProposal: initial `pending`; transitions
 * `pending -> approved` and `pending -> rejected`. A decided proposal
 * never changes again; after a rejection the student proposes anew.
 */
enum TopicProposalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

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
            self::Pending => [self::Approved, self::Rejected],
            self::Approved, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}

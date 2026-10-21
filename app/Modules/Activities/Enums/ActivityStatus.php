<?php

namespace App\Modules\Activities\Enums;

/**
 * State model of ENT-Activity: initial `draft`;
 * transitions `draft -> published` and `published -> closed`.
 */
enum ActivityStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    public static function initial(): self
    {
        return self::Draft;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Published],
            self::Published => [self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}

<?php

namespace App\Modules\Courses\Enums;

/**
 * State model of ENT-CourseSpace: initial `active`;
 * transitions `active -> archived` and `archived -> active`.
 */
enum CourseSpaceStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    public static function initial(): self
    {
        return self::Active;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Active => [self::Archived],
            self::Archived => [self::Active],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}

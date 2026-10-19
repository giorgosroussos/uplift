<?php

namespace App\Modules\Tenancy\Enums;

/**
 * State model of ENT-Organization: initial `active`;
 * transitions `active -> suspended` and `suspended -> active`.
 */
enum OrganizationStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

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
            self::Active => [self::Suspended],
            self::Suspended => [self::Active],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}

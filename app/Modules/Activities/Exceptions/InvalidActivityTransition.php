<?php

namespace App\Modules\Activities\Exceptions;

use App\Modules\Activities\Enums\ActivityStatus;
use DomainException;

final class InvalidActivityTransition extends DomainException
{
    public static function between(ActivityStatus $from, ActivityStatus $to): self
    {
        return new self("Activity status cannot change from [{$from->value}] to [{$to->value}].");
    }

    public static function initial(ActivityStatus $status): self
    {
        $initial = ActivityStatus::initial()->value;

        return new self("An activity must be created as [{$initial}], not [{$status->value}].");
    }
}

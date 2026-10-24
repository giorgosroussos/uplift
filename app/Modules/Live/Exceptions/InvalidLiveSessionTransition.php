<?php

namespace App\Modules\Live\Exceptions;

use App\Modules\Live\Enums\LiveSessionStatus;
use DomainException;

/**
 * A change of status that the state model of ENT-LiveSession does not
 * allow. The message names only the states.
 */
final class InvalidLiveSessionTransition extends DomainException
{
    public static function between(LiveSessionStatus $from, LiveSessionStatus $to): self
    {
        return new self("Live session status cannot change from [{$from->value}] to [{$to->value}].");
    }

    public static function initial(LiveSessionStatus $status): self
    {
        $initial = LiveSessionStatus::initial()->value;

        return new self("A live session must be created as [{$initial}], not [{$status->value}].");
    }
}

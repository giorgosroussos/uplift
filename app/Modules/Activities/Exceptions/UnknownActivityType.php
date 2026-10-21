<?php

namespace App\Modules\Activities\Exceptions;

use App\Modules\Activities\Enums\ActivityKind;
use DomainException;

final class UnknownActivityType extends DomainException
{
    public static function for(ActivityKind $kind): self
    {
        return new self("No ActivityType is registered for [{$kind->value}].");
    }
}

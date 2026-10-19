<?php

namespace App\Modules\Tenancy\Exceptions;

use App\Modules\Tenancy\Enums\OrganizationStatus;
use DomainException;

final class InvalidOrganizationTransition extends DomainException
{
    public static function between(OrganizationStatus $from, OrganizationStatus $to): self
    {
        return new self("Organization status cannot change from [{$from->value}] to [{$to->value}].");
    }

    public static function initial(OrganizationStatus $status): self
    {
        $initial = OrganizationStatus::initial()->value;

        return new self("An organization must be created as [{$initial}], not [{$status->value}].");
    }
}

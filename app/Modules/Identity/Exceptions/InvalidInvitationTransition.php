<?php

namespace App\Modules\Identity\Exceptions;

use App\Modules\Identity\Enums\InvitationStatus;
use DomainException;

/**
 * A change of invitation status that the state model does not allow. The
 * message names the states only, never the invited email address.
 */
final class InvalidInvitationTransition extends DomainException
{
    public static function between(InvitationStatus $from, InvitationStatus $to): self
    {
        return new self("Invitation status cannot change from [{$from->value}] to [{$to->value}].");
    }

    public static function initial(InvitationStatus $status): self
    {
        $initial = InvitationStatus::initial()->value;

        return new self("An invitation must be created as [{$initial}], not [{$status->value}].");
    }
}

<?php

namespace App\Modules\Courses\Exceptions;

use App\Modules\Courses\Enums\GroupMembershipStatus;
use DomainException;

final class InvalidGroupMembershipTransition extends DomainException
{
    public static function between(GroupMembershipStatus $from, GroupMembershipStatus $to): self
    {
        return new self("Group membership status cannot change from [{$from->value}] to [{$to->value}].");
    }

    public static function initial(GroupMembershipStatus $status): self
    {
        return new self("A group membership cannot be created as [{$status->value}]; only invitations and the creator's own membership are created.");
    }
}

<?php

namespace App\Modules\Activities\Exceptions;

use App\Modules\Activities\Enums\TopicProposalStatus;
use DomainException;

/**
 * A topic proposal status change outside its state model. The message
 * names the statuses only, never the proposal's content.
 */
final class InvalidTopicProposalTransition extends DomainException
{
    public static function between(TopicProposalStatus $from, TopicProposalStatus $to): self
    {
        return new self("Topic proposal status cannot change from [{$from->value}] to [{$to->value}].");
    }

    public static function initial(TopicProposalStatus $status): self
    {
        $initial = TopicProposalStatus::initial()->value;

        return new self("A topic proposal must be created as [{$initial}], not [{$status->value}].");
    }
}

<?php

namespace App\Modules\Assessment\Exceptions;

use DomainException;

/**
 * An answer arrived for an attempt that is no longer in progress (time up,
 * submitted or closed); it is not counted (DATA-004). Carries no answer
 * data (SEC-003).
 */
final class AttemptClosed extends DomainException
{
    public static function make(): self
    {
        return new self('The attempt is no longer in progress.');
    }
}

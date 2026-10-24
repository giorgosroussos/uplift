<?php

namespace App\Modules\Live\Exceptions;

use DomainException;

/**
 * An answer or submission reached a participation that no longer takes
 * answers: the participant has submitted, or the session is not running
 * or its common end time has passed (SCOPE-021). Nothing was saved. The
 * message never contains the answer (SEC-003).
 */
final class LiveSessionClosed extends DomainException
{
    public static function make(): self
    {
        return new self('The live participation no longer takes answers.');
    }
}

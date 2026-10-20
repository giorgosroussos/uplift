<?php

namespace App\Modules\Audit\Models;

use LogicException;

/**
 * Thrown when code tries to change or delete an audit entry (SEC-010).
 */
final class AppendOnlyViolation extends LogicException
{
    public static function for(string $operation): self
    {
        return new self("Audit entries are append-only; {$operation} is not allowed.");
    }
}

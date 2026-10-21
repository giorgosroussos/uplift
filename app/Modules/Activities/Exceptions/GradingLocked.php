<?php

namespace App\Modules\Activities\Exceptions;

use DomainException;

/**
 * UC-022: a published activity with submissions keeps every setting that
 * changes grading.
 */
final class GradingLocked extends DomainException
{
    public static function make(): self
    {
        return new self('The activity has submissions; settings that change grading cannot be edited.');
    }
}

<?php

namespace App\Modules\Assessment\Exceptions;

use DomainException;

/**
 * A change of state that the state model of ENT-QuizAttempt or ENT-Answer
 * does not allow. The message names only the entity and the states, never
 * answers, feedback or points (SEC-003).
 */
final class InvalidStateTransition extends DomainException
{
    public static function between(string $entity, string $from, string $to): self
    {
        return new self("{$entity} cannot change from [{$from}] to [{$to}].");
    }

    public static function initial(string $entity, string $initial, string $given): self
    {
        return new self("{$entity} must be created as [{$initial}], not [{$given}].");
    }
}

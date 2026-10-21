<?php

namespace App\Modules\Assessment\Enums;

/**
 * State model of ENT-QuizAttempt: initial `in_progress`; transitions
 * `in_progress -> submitted` and `submitted -> graded`.
 */
enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Graded = 'graded';

    public static function initial(): self
    {
        return self::InProgress;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::InProgress => [self::Submitted],
            self::Submitted => [self::Graded],
            self::Graded => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}

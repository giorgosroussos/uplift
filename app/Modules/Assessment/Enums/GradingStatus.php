<?php

namespace App\Modules\Assessment\Enums;

/**
 * ENT-Answer.grading_status with the state model of ENT-Answer: initial
 * `pending`; the only transition is `pending -> graded`.
 */
enum GradingStatus: string
{
    case AutoGraded = 'auto_graded';
    case Pending = 'pending';
    case Graded = 'graded';

    public static function initial(): self
    {
        return self::Pending;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Graded],
            self::AutoGraded, self::Graded => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}

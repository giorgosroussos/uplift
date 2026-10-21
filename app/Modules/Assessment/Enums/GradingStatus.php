<?php

namespace App\Modules\Assessment\Enums;

/**
 * ENT-Answer.grading_status with the state model of ENT-Answer (SCOPE-023):
 * initial `pending` (answers are autosaved before submission); on
 * submission choice, true/false and matching answers move to `auto_graded`
 * and free-text answers stay `pending`; transitions `pending -> auto_graded`,
 * `pending -> graded` and `auto_graded -> graded` (teacher regrade).
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
            self::Pending => [self::AutoGraded, self::Graded],
            self::AutoGraded => [self::Graded],
            self::Graded => [],
        };
    }

    /**
     * SCOPE-023: the status an answer to a question of this type moves to
     * when its attempt is submitted.
     */
    public static function onSubmission(QuestionType $type): self
    {
        return $type->isAutoGraded() ? self::AutoGraded : self::Pending;
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}

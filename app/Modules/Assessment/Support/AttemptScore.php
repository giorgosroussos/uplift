<?php

namespace App\Modules\Assessment\Support;

use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;

/**
 * The score of an attempt as its student may see it (UC-012 step 5): the
 * earned and maximum points as exact decimal strings, provisional while
 * free-text answers wait for grading; null while the quiz's score
 * visibility hides it. Conversion to the organisation's grading scale
 * happens at display time and comes with grading (P3-T5).
 */
final class AttemptScore
{
    /**
     * @return array{earned: string, max: string, provisional: bool}|null
     */
    public static function visibleTo(Quiz $quiz, QuizAttempt $attempt): ?array
    {
        if (! $quiz->isScoreVisible($attempt)) {
            return null;
        }

        return [
            'earned' => (string) ($attempt->earned_points ?? '0.00'),
            'max' => (string) ($attempt->max_points ?? '0.00'),
            'provisional' => $attempt->status !== AttemptStatus::Graded,
        ];
    }
}

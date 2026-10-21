<?php

namespace App\Modules\Assessment\Support;

use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Tenancy\Enums\GradingScale;

/**
 * The score of an attempt (UC-012 step 5, UC-017): the earned and maximum
 * points as exact decimal strings, provisional while free-text answers
 * wait for grading, and the grade on the organisation's grading scale,
 * converted only here, at display time (DATA-003, DATA-005).
 */
final class AttemptScore
{
    /**
     * The score as the attempt's student may see it: null while the quiz's
     * score visibility hides it.
     *
     * @return array{earned: string, max: string, provisional: bool, grade: array{scale: string, value: string|null, percentage: string|null, earned: string, max: string, passed: bool|null}}|null
     */
    public static function visibleTo(Quiz $quiz, QuizAttempt $attempt, ?GradingScale $scale = null): ?array
    {
        if (! $quiz->isScoreVisible($attempt)) {
            return null;
        }

        return self::of($quiz, $attempt, $scale);
    }

    /**
     * The score regardless of visibility (the owning teacher).
     *
     * @return array{earned: string, max: string, provisional: bool, grade: array{scale: string, value: string|null, percentage: string|null, earned: string, max: string, passed: bool|null}}
     */
    public static function of(Quiz $quiz, QuizAttempt $attempt, ?GradingScale $scale = null): array
    {
        $earned = (string) ($attempt->earned_points ?? '0.00');
        $max = (string) ($attempt->max_points ?? '0.00');

        return [
            'earned' => $earned,
            'max' => $max,
            'provisional' => $attempt->status !== AttemptStatus::Graded,
            'grade' => Grade::of(
                $earned,
                $max,
                $scale ?? $attempt->organization->grading_scale,
                $quiz->pass_mark_percentage === null ? null : (string) $quiz->pass_mark_percentage,
            ),
        ];
    }
}

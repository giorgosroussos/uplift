<?php

namespace App\Modules\Activities\Enums;

/**
 * Status of a submission in the submissions overview (UC-028).
 */
enum SubmissionStatus: string
{
    case Submitted = 'submitted';
    case NeedsGrading = 'needs_grading';
    case Graded = 'graded';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}

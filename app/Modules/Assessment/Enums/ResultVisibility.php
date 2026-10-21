<?php

namespace App\Modules\Assessment\Enums;

/**
 * When students see their score (ENT-Quiz.score_visibility) or the correct
 * answers (ENT-Quiz.answers_visibility).
 */
enum ResultVisibility: string
{
    case Immediate = 'immediate';
    case AfterDeadline = 'after_deadline';
    case Hidden = 'hidden';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $visibility): string => $visibility->value, self::cases());
    }
}

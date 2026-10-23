<?php

namespace App\Modules\Activities\Enums;

/**
 * ENT-Poll.results_visibility (SCOPE-013): a student sees the results
 * right after voting, or only once the poll is closed.
 */
enum PollResultsVisibility: string
{
    case AfterVote = 'after_vote';
    case AfterClose = 'after_close';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $visibility): string => $visibility->value, self::cases());
    }
}

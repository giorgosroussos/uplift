<?php

namespace App\Modules\Live\Support;

use App\Modules\Assessment\Models\MatchingPair;
use App\Modules\Assessment\Models\Question;
use App\Modules\Live\Models\LiveParticipant;
use Illuminate\Support\Facades\Config;

/**
 * The two sides of a matching question as a participant sees them in a
 * live session. Left items carry their pair id; right items carry an
 * opaque per-participant id and are ordered by it, so neither their ids
 * nor their order give away which left item they belong to (SCOPE-021:
 * no correctness before the session finishes).
 */
final class LiveMatchingItems
{
    public static function rightId(LiveParticipant $participant, string $pairId): string
    {
        return substr(hash_hmac('sha256', 'live|'.$participant->id.'|'.$pairId, (string) Config::get('app.key')), 0, 24);
    }

    /**
     * @return list<array{id: string, text: string}>
     */
    public static function left(Question $question): array
    {
        return $question->pairs
            ->map(fn (MatchingPair $pair): array => ['id' => $pair->id, 'text' => $pair->left_text])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, text: string}>
     */
    public static function right(LiveParticipant $participant, Question $question): array
    {
        return $question->pairs
            ->map(fn (MatchingPair $pair): array => ['id' => self::rightId($participant, $pair->id), 'text' => $pair->right_text])
            ->sortBy('id')
            ->values()
            ->all();
    }

    /**
     * Opaque right id => pair id.
     *
     * @return array<string, string>
     */
    public static function rightPairIds(LiveParticipant $participant, Question $question): array
    {
        $map = [];

        foreach ($question->pairs as $pair) {
            $map[self::rightId($participant, $pair->id)] = $pair->id;
        }

        return $map;
    }
}

<?php

namespace App\Modules\Assessment\Support;

use App\Modules\Assessment\Models\MatchingPair;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\QuizAttempt;
use Illuminate\Support\Facades\Config;

/**
 * The two sides of a matching question as a student sees them in an
 * attempt. Left items carry their pair id; right items carry an opaque
 * per-attempt id and are shuffled per attempt, so neither their ids nor
 * their order give away which left item they belong to.
 */
final class MatchingItems
{
    public static function rightId(QuizAttempt $attempt, string $pairId): string
    {
        return substr(hash_hmac('sha256', $attempt->id.'|'.$pairId, (string) Config::get('app.key')), 0, 24);
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
    public static function right(QuizAttempt $attempt, Question $question): array
    {
        return $question->pairs
            ->map(fn (MatchingPair $pair): array => ['id' => self::rightId($attempt, $pair->id), 'text' => $pair->right_text])
            ->sortBy('id')
            ->values()
            ->all();
    }

    /**
     * Opaque right id => pair id.
     *
     * @return array<string, string>
     */
    public static function rightPairIds(QuizAttempt $attempt, Question $question): array
    {
        $map = [];

        foreach ($question->pairs as $pair) {
            $map[self::rightId($attempt, $pair->id)] = $pair->id;
        }

        return $map;
    }
}

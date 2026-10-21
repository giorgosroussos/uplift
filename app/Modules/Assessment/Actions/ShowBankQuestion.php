<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Models\ChoiceOption;
use App\Modules\Assessment\Models\MatchingPair;
use App\Modules\Assessment\Models\Question;

/**
 * UC-023: a bank question with its options and pairs in order, for the
 * builder. True/false option texts stay 'true' and 'false'; the page shows
 * them through translation keys (DATA-016).
 */
class ShowBankQuestion
{
    /**
     * @return array{id: string, type: string, prompt: string, points: string, options: list<array{id: string, text: string, is_correct: bool}>, pairs: list<array{id: string, left_text: string, right_text: string}>}
     */
    public function handle(Question $question): array
    {
        $question->loadMissing(['options', 'pairs']);

        return [
            'id' => $question->id,
            'type' => $question->type->value,
            'prompt' => $question->prompt,
            'points' => $question->points,
            'options' => $question->options
                ->map(fn (ChoiceOption $option): array => [
                    'id' => $option->id,
                    'text' => $option->text,
                    'is_correct' => $option->is_correct,
                ])
                ->values()
                ->all(),
            'pairs' => $question->pairs
                ->map(fn (MatchingPair $pair): array => [
                    'id' => $pair->id,
                    'left_text' => $pair->left_text,
                    'right_text' => $pair->right_text,
                ])
                ->values()
                ->all(),
        ];
    }
}

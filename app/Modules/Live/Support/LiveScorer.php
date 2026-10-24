<?php

namespace App\Modules\Live\Support;

use App\Modules\Assessment\Enums\QuestionType;
use App\Modules\Assessment\Models\ChoiceOption;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Support\AnswerScorer;
use App\Modules\Assessment\Support\Points;
use App\Modules\Live\Models\LiveAnswer;

/**
 * Scores a live answer with the quiz scoring rules (SCOPE-024) and partial
 * credit, never with negative marking (SCOPE-022), in exact hundredths:
 *
 * - single choice and true/false: all points or none;
 * - multiple choice: points x max(0, correct selected - wrong selected) / correct options;
 * - matching: points x correct matches / pairs.
 *
 * An answer is correct when it earns the question's full points.
 */
final class LiveScorer
{
    /**
     * @return array{int, bool} points in hundredths and whether the answer is correct
     */
    public static function score(LiveAnswer $answer, Question $question): array
    {
        $points = Points::toHundredths((string) $question->points);

        $credit = match ($question->type) {
            QuestionType::SingleChoice, QuestionType::TrueFalse, QuestionType::MultipleChoice => self::choice($answer, $question, $points),
            QuestionType::Matching => self::matching($answer, $question, $points),
            // Live quizzes have no free text (SCOPE-019); nothing to score.
            QuestionType::FreeText => 0,
        };

        return [$credit, $points > 0 && $credit === $points];
    }

    private static function choice(LiveAnswer $answer, Question $question, int $points): int
    {
        $selected = array_values(array_filter(
            (array) ($answer->response['option_ids'] ?? []),
            'is_string',
        ));
        $correct = $question->options
            ->filter(fn (ChoiceOption $o): bool => $o->is_correct)
            ->map(fn (ChoiceOption $o): string => $o->id)
            ->values()
            ->all();

        return AnswerScorer::choice($question->type, $points, $correct, $selected);
    }

    /**
     * A match is correct when the left item and the right item come from
     * the same pair.
     */
    private static function matching(LiveAnswer $answer, Question $question, int $points): int
    {
        $correct = 0;

        foreach ((array) ($answer->response['matches'] ?? []) as $match) {
            if (is_array($match) && isset($match['left_pair_id']) && ($match['left_pair_id'] === ($match['right_pair_id'] ?? null))) {
                $correct++;
            }
        }

        return AnswerScorer::matching($points, $question->pairs->count(), $correct);
    }
}

<?php

namespace App\Modules\Assessment\Support;

use App\Modules\Assessment\Enums\QuestionType;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\AnswerMatch;
use App\Modules\Assessment\Models\AnswerSelection;
use App\Modules\Assessment\Models\ChoiceOption;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\Quiz;
use LogicException;

/**
 * Auto-grading of one answer (SCOPE-024), in exact hundredths of a point:
 *
 * - single choice and true/false: all points or none;
 * - multiple choice: points x max(0, correct selected - wrong selected) / correct options;
 * - matching: points x correct matches / pairs;
 * - each result is rounded half-up to 2 decimals;
 * - with negative marking, an answered question that earns 0 scores
 *   -(fraction % of its points); partial credit, unanswered questions and
 *   free text are never penalised.
 */
final class AnswerScorer
{
    /**
     * Points awarded to an auto-graded answer, in hundredths.
     */
    public function score(Answer $answer, Question $question, Quiz $quiz): int
    {
        $points = Points::toHundredths((string) $question->points);

        [$credit, $answered] = match ($question->type) {
            QuestionType::SingleChoice, QuestionType::TrueFalse, QuestionType::MultipleChoice => $this->choiceAnswer($answer, $question, $points),
            QuestionType::Matching => $this->matchingAnswer($answer, $question, $points),
            QuestionType::FreeText => throw new LogicException('Free-text answers are graded by the teacher.'),
        };

        return self::withNegativeMarking($credit, $answered, $points, $quiz->negative_marking ? $quiz->negative_mark_fraction : null);
    }

    /**
     * Credit of a choice answer, before negative marking.
     *
     * @param  list<string>  $correctIds
     * @param  list<string>  $selectedIds
     */
    public static function choice(QuestionType $type, int $points, array $correctIds, array $selectedIds): int
    {
        $selectedIds = array_values(array_unique($selectedIds));
        $correctSelected = count(array_intersect($selectedIds, $correctIds));
        $wrongSelected = count($selectedIds) - $correctSelected;

        if ($type === QuestionType::MultipleChoice) {
            if ($correctIds === []) {
                return 0;
            }

            return Points::divideHalfUp($points * max(0, $correctSelected - $wrongSelected), count($correctIds));
        }

        // Single choice and true/false: exactly the one correct option.
        return $correctSelected === 1 && $wrongSelected === 0 ? $points : 0;
    }

    /**
     * Credit of a matching answer, before negative marking.
     */
    public static function matching(int $points, int $pairs, int $correctMatches): int
    {
        if ($pairs <= 0) {
            return 0;
        }

        return Points::divideHalfUp($points * min($correctMatches, $pairs), $pairs);
    }

    /**
     * @param  string|null  $fraction  the deduction in % of the points, or null without negative marking
     */
    public static function withNegativeMarking(int $credit, bool $answered, int $points, ?string $fraction): int
    {
        if ($fraction === null || ! $answered || $credit !== 0) {
            return $credit;
        }

        // % with 2 decimals = hundredths of a percent; 100 % = 10000.
        return -Points::divideHalfUp($points * Points::toHundredths($fraction), 10000);
    }

    /**
     * @return array{int, bool} credit and whether anything was answered
     */
    private function choiceAnswer(Answer $answer, Question $question, int $points): array
    {
        $selected = $answer->selections->map(fn (AnswerSelection $s): string => $s->choice_option_id)->values()->all();
        $correct = $question->options
            ->filter(fn (ChoiceOption $o): bool => $o->is_correct)
            ->map(fn (ChoiceOption $o): string => $o->id)
            ->values()
            ->all();

        return [self::choice($question->type, $points, $correct, $selected), $selected !== []];
    }

    /**
     * A match is correct when the left item and the right item come from
     * the same pair.
     *
     * @return array{int, bool}
     */
    private function matchingAnswer(Answer $answer, Question $question, int $points): array
    {
        $correct = $answer->matches->filter(fn (AnswerMatch $m): bool => $m->left_pair_id === $m->right_pair_id)->count();

        return [self::matching($points, $question->pairs->count(), $correct), $answer->matches->isNotEmpty()];
    }
}

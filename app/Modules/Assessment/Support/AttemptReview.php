<?php

namespace App\Modules\Assessment\Support;

use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\AnswerMatch;
use App\Modules\Assessment\Models\AnswerSelection;
use App\Modules\Assessment\Models\ChoiceOption;
use App\Modules\Assessment\Models\MatchingPair;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizQuestion;

/**
 * The questions of a submitted attempt with the answers given, as the
 * result (UC-017) and the grading page (UC-026) show them: per question
 * the selected options or matches or the text, the grading status, the
 * points awarded and the teacher's feedback. Which options and pairs are
 * correct is included only when asked for (the student sees them only if
 * the quiz's answer visibility allows it); the points only when the score
 * may be seen.
 */
final class AttemptReview
{
    /**
     * @return list<array{id: string, answer_id: string|null, type: string, prompt: string, points: string, options: list<array{id: string, text: string, selected: bool, correct: bool|null}>, matches: list<array{left: string, chosen: string|null, correct: string|null, is_correct: bool|null}>, text: string|null, grading_status: string|null, points_awarded: string|null, feedback: string|null}>
     */
    public static function questions(QuizAttempt $attempt, bool $showCorrect, bool $showPoints): array
    {
        $answers = $attempt->answers()->with(['selections', 'matches'])->get()->keyBy('quiz_question_id');
        $placements = $attempt->quiz->quizQuestions()->with(['question.options', 'question.pairs'])->get();

        return $placements
            ->map(fn (QuizQuestion $placement): array => self::question($placement, $answers->get($placement->id), $showCorrect, $showPoints))
            ->values()
            ->all();
    }

    /**
     * @return array{id: string, answer_id: string|null, type: string, prompt: string, points: string, options: list<array{id: string, text: string, selected: bool, correct: bool|null}>, matches: list<array{left: string, chosen: string|null, correct: string|null, is_correct: bool|null}>, text: string|null, grading_status: string|null, points_awarded: string|null, feedback: string|null}
     */
    private static function question(QuizQuestion $placement, ?Answer $answer, bool $showCorrect, bool $showPoints): array
    {
        $question = $placement->question;
        $selected = $answer?->selections->map(fn (AnswerSelection $s): string => $s->choice_option_id)->all() ?? [];
        /** @var array<string, string> $chosen left pair id => right pair id */
        $chosen = $answer?->matches->mapWithKeys(fn (AnswerMatch $m): array => [$m->left_pair_id => $m->right_pair_id])->all() ?? [];
        $rightTexts = $question->pairs->mapWithKeys(fn (MatchingPair $pair): array => [$pair->id => $pair->right_text])->all();

        return [
            'id' => $placement->id,
            'answer_id' => $answer?->id,
            'type' => $question->type->value,
            'prompt' => $question->prompt,
            'points' => (string) $question->points,
            'options' => $question->options->map(fn (ChoiceOption $option): array => [
                'id' => $option->id,
                'text' => $option->text,
                'selected' => in_array($option->id, $selected, true),
                'correct' => $showCorrect ? $option->is_correct : null,
            ])->values()->all(),
            'matches' => $question->type->hasPairs() ? $question->pairs->map(fn (MatchingPair $pair): array => [
                'left' => $pair->left_text,
                'chosen' => isset($chosen[$pair->id]) ? ($rightTexts[$chosen[$pair->id]] ?? null) : null,
                'correct' => $showCorrect ? $pair->right_text : null,
                'is_correct' => $showCorrect ? ($chosen[$pair->id] ?? null) === $pair->id : null,
            ])->values()->all() : [],
            'text' => $answer?->text_response,
            'grading_status' => $answer?->grading_status->value,
            'points_awarded' => $showPoints && $answer?->points_awarded !== null ? (string) $answer->points_awarded : null,
            'feedback' => $answer?->feedback,
        ];
    }
}

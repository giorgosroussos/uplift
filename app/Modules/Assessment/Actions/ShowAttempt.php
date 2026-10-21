<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\AnswerMatch;
use App\Modules\Assessment\Models\AnswerSelection;
use App\Modules\Assessment\Models\ChoiceOption;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Assessment\Support\AttemptScore;
use App\Modules\Assessment\Support\MatchingItems;
use Carbon\CarbonImmutable;

/**
 * UC-012: one attempt for its student: the questions in order (never which
 * options or pairs are correct), the answers saved so far, the time left
 * by the server's clock (DATA-004) and, once submitted, the score if the
 * quiz shows it. An attempt whose time is up is submitted first
 * (SCOPE-024).
 */
class ShowAttempt
{
    public function __construct(private readonly SubmitQuizAttempt $submit) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(QuizAttempt $attempt): array
    {
        $this->submit->submitIfDue($attempt);

        $quiz = $attempt->quiz;
        $activity = $quiz->activity;
        $now = CarbonImmutable::now();

        $answers = $attempt->answers()->with(['selections', 'matches'])->get()->keyBy('quiz_question_id');
        $placements = $quiz->quizQuestions()->with(['question.options', 'question.pairs'])->get();

        $number = QuizAttempt::query()
            ->where('quiz_id', $attempt->quiz_id)
            ->where('student_id', $attempt->student_id)
            ->where('started_at', '<=', $attempt->started_at)
            ->count();

        return [
            'quiz' => [
                'activity_id' => $activity->id,
                'title' => $activity->title,
            ],
            'attempt' => [
                'id' => $attempt->id,
                'number' => $number,
                'status' => $attempt->status->value,
                'started_at' => $attempt->started_at->toIso8601String(),
                'ends_at' => $attempt->ends_at?->toIso8601String(),
                'remaining_seconds' => $attempt->ends_at === null ? null : max(0, (int) $now->diffInSeconds($attempt->ends_at, false)),
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            ],
            'questions' => $placements->map(fn (QuizQuestion $placement): array => $this->question($attempt, $placement))->values()->all(),
            'answers' => $placements
                ->filter(fn (QuizQuestion $placement): bool => $answers->has($placement->id))
                ->mapWithKeys(fn (QuizQuestion $placement): array => [$placement->id => $this->answer($attempt, $answers->get($placement->id))])
                ->all(),
            'score' => AttemptScore::visibleTo($quiz, $attempt),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function question(QuizAttempt $attempt, QuizQuestion $placement): array
    {
        $question = $placement->question;

        return [
            'id' => $placement->id,
            'type' => $question->type->value,
            'prompt' => $question->prompt,
            'points' => (string) $question->points,
            'options' => $question->options
                ->map(fn (ChoiceOption $option): array => ['id' => $option->id, 'text' => $option->text])
                ->values()
                ->all(),
            'left' => $question->type->hasPairs() ? MatchingItems::left($question) : [],
            'right' => $question->type->hasPairs() ? MatchingItems::right($attempt, $question) : [],
        ];
    }

    /**
     * @return array{option_ids: list<string>, matches: list<array{left_id: string, right_id: string}>, text: string|null}
     */
    private function answer(QuizAttempt $attempt, ?Answer $answer): array
    {
        return [
            'option_ids' => $answer?->selections->map(fn (AnswerSelection $s): string => $s->choice_option_id)->values()->all() ?? [],
            'matches' => $answer?->matches->map(fn (AnswerMatch $m): array => [
                'left_id' => $m->left_pair_id,
                'right_id' => MatchingItems::rightId($attempt, $m->right_pair_id),
            ])->values()->all() ?? [],
            'text' => $answer?->text_response,
        ];
    }
}

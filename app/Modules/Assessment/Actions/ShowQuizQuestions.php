<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizQuestion;

/**
 * UC-022: the questions of a quiz in their order, with their options and
 * pairs, in the shape of the wizard's content step (the same shape as a
 * bank question in the builder).
 */
class ShowQuizQuestions
{
    public function __construct(private readonly ShowBankQuestion $show) {}

    /**
     * @return list<array{id: string, type: string, prompt: string, points: string, options: list<array{id: string, text: string, is_correct: bool}>, pairs: list<array{id: string, left_text: string, right_text: string}>}>
     */
    public function handle(Quiz $quiz): array
    {
        return $quiz->quizQuestions()
            ->with(['question.options', 'question.pairs'])
            ->get()
            ->map(fn (QuizQuestion $placement): array => $this->show->handle($placement->question))
            ->values()
            ->all();
    }
}

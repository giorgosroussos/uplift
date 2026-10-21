<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Models\Question;
use App\Modules\Identity\Models\User;

/**
 * UC-022 and DATA-008: the teacher's own bank questions, newest first, with
 * their options and pairs, for copying into a quiz in the wizard. Only the
 * teacher's in_bank questions (OWN-016); copies placed in quizzes are not
 * part of any bank.
 */
class ListCopyableBankQuestions
{
    public function __construct(private readonly ShowBankQuestion $show) {}

    /**
     * @return list<array{id: string, type: string, prompt: string, points: string, options: list<array{id: string, text: string, is_correct: bool}>, pairs: list<array{id: string, left_text: string, right_text: string}>}>
     */
    public function handle(User $teacher): array
    {
        return Question::query()
            ->where('owner_id', $teacher->getKey())
            ->where('in_bank', true)
            ->with(['options', 'pairs'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Question $question): array => $this->show->handle($question))
            ->values()
            ->all();
    }
}

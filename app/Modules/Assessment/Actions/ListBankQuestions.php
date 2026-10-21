<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Models\Question;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * UC-023 and DATA-016: the teacher's own question bank, newest first
 * (OWN-016: only in_bank questions owned by the teacher, of the current
 * tenant).
 */
class ListBankQuestions
{
    public const int PER_PAGE = 20;

    /**
     * @return LengthAwarePaginator<int, array{id: string, type: string, prompt: string, points: string, items_count: int, created_at: string|null}>
     */
    public function handle(User $teacher): LengthAwarePaginator
    {
        return Question::query()
            ->where('owner_id', $teacher->getKey())
            ->where('in_bank', true)
            ->withCount(['options', 'pairs'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->through(fn (Question $question): array => [
                'id' => $question->id,
                'type' => $question->type->value,
                'prompt' => $question->prompt,
                'points' => $question->points,
                'items_count' => (int) $question->getAttribute('options_count') + (int) $question->getAttribute('pairs_count'),
                'created_at' => $question->created_at?->toIso8601String(),
            ]);
    }
}

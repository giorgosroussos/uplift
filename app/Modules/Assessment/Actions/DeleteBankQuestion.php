<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Models\Question;

/**
 * UC-023 and DATA-016: a teacher hard-deletes a question of their bank; its
 * options and pairs go with it (on delete cascade). Quiz copies are separate
 * questions and stay as they are.
 */
class DeleteBankQuestion
{
    public function handle(Question $question): void
    {
        $question->delete();
    }
}

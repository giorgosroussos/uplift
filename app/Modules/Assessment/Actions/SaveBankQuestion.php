<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Enums\QuestionType;
use App\Modules\Assessment\Models\ChoiceOption;
use App\Modules\Assessment\Models\MatchingPair;
use App\Modules\Assessment\Models\Question;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * UC-023: creates a question in the teacher's bank or saves an edit of one.
 * The input is validated per DATA-016 (Http/Requests/QuestionRequest).
 * Options and pairs are stored in the given order (position = index); an
 * item with the id of an existing one of this question is kept and
 * updated, the others are created, and the ones left out are deleted.
 * Quiz copies are separate questions and are never touched (DATA-016).
 */
class SaveBankQuestion
{
    /**
     * @param  array{type: QuestionType, prompt: string, points: string, options: list<array{id: string|null, text: string, is_correct: bool}>, pairs: list<array{id: string|null, left_text: string, right_text: string}>}  $input
     */
    public function handle(User $owner, ?Question $question, array $input): Question
    {
        return DB::transaction(function () use ($owner, $question, $input): Question {
            if ($question === null) {
                $question = new Question;
                $question->organization_id = $owner->organization_id;
                $question->owner_id = (string) $owner->getKey();
                $question->in_bank = true;
            }

            $question->fill([
                'type' => $input['type'],
                'prompt' => $input['prompt'],
                'points' => $input['points'],
            ])->save();

            $this->sync($question, $question->options(), array_map(fn (array $option): array => [
                'id' => $option['id'],
                'attributes' => ['text' => $option['text'], 'is_correct' => $option['is_correct']],
            ], $input['type']->hasOptions() ? $input['options'] : []));

            $this->sync($question, $question->pairs(), array_map(fn (array $pair): array => [
                'id' => $pair['id'],
                'attributes' => ['left_text' => $pair['left_text'], 'right_text' => $pair['right_text']],
            ], $input['type']->hasPairs() ? $input['pairs'] : []));

            return $question->load(['options', 'pairs']);
        });
    }

    /**
     * @template TItem of ChoiceOption|MatchingPair
     *
     * @param  HasMany<TItem, Question>  $relation
     * @param  list<array{id: string|null, attributes: array<string, mixed>}>  $items
     */
    private function sync(Question $question, HasMany $relation, array $items): void
    {
        /** @var array<string, TItem> $existing */
        $existing = $relation->get()->keyBy(fn (Model $item): string => (string) $item->getKey())->all();
        $kept = [];

        foreach ($items as $position => $item) {
            $model = $item['id'] !== null && isset($existing[$item['id']]) && ! isset($kept[$item['id']])
                ? $existing[$item['id']]
                : $relation->make();

            $model->fill([...$item['attributes'], 'position' => $position]);
            $model->organization_id = $question->organization_id;
            $model->save();

            $kept[(string) $model->getKey()] = true;
        }

        $removed = array_diff(array_keys($existing), array_keys($kept));

        if ($removed !== []) {
            $relation->getQuery()->whereKey($removed)->delete();
        }
    }
}

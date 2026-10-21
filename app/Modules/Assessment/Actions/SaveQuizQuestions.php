<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\QuestionType;
use App\Modules\Assessment\Models\ChoiceOption;
use App\Modules\Assessment\Models\MatchingPair;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Assessment\Support\QuestionRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * UC-022 step 3: the questions of a quiz (wizard step "content"), in their
 * order. Each question is the quiz's own copy (in_bank = false, DATA-008):
 * a question copied from the bank arrives as a new question with its
 * options and pairs, so later edits or deletions of the bank question never
 * reach the quiz (DATA-016). Every question is validated per DATA-016; a
 * live quiz takes no free-text question.
 *
 * The client identifies every question, option and pair by a UUID, which
 * it generates for new ones, so autosaving the same list again changes
 * nothing. An id that belongs to a row outside this quiz is refused, so a
 * teacher can never reach another question (OWN-016). Questions left out
 * are deleted. Called by the quiz's ActivityType inside the wizard's
 * transaction, after the grading lock was checked.
 */
class SaveQuizQuestions
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Activity $activity, array $input): void
    {
        $quiz = Quiz::forActivity($activity);

        if ($quiz === null) {
            throw ValidationException::withMessages(['questions' => (string) __('assessment.quiz.errors.settings_first')]);
        }

        /** @var array<string, QuizQuestion> $placed */
        $placed = $quiz->quizQuestions()->with(['question.options', 'question.pairs'])->get()
            ->keyBy('question_id')->all();
        $questions = $this->validate($quiz, $placed, is_array($input['questions'] ?? null) ? array_values($input['questions']) : []);
        $ownerId = $activity->courseSpace->teacher_id;
        $kept = [];

        foreach ($questions as $position => $item) {
            $placement = $placed[$item['id']] ?? null;
            $question = $placement?->question;

            if ($question === null) {
                $question = new Question;
                $question->id = $item['id'];
                $question->organization_id = $quiz->organization_id;
                $question->owner_id = $ownerId;
                $question->in_bank = false;
            }

            $question->fill([
                'type' => $item['type'],
                'prompt' => $item['prompt'],
                'points' => $item['points'],
            ])->save();

            $this->sync($question, $question->options(), array_map(fn (array $option): array => [
                'id' => $option['id'],
                'attributes' => ['text' => $option['text'], 'is_correct' => $option['is_correct']],
            ], $item['options']));

            $this->sync($question, $question->pairs(), array_map(fn (array $pair): array => [
                'id' => $pair['id'],
                'attributes' => ['left_text' => $pair['left_text'], 'right_text' => $pair['right_text']],
            ], $item['pairs']));

            if ($placement === null) {
                $placement = new QuizQuestion;
                $placement->organization_id = $quiz->organization_id;
                $placement->quiz_id = $quiz->activity_id;
                $placement->question_id = $question->id;
            }

            $placement->position = $position;
            $placement->save();

            $kept[$question->id] = true;
        }

        $removed = array_values(array_diff(array_keys($placed), array_keys($kept)));

        if ($removed !== []) {
            // The placements go with their questions (on delete cascade).
            Question::query()->whereKey($removed)->where('in_bank', false)->delete();
        }
    }

    /**
     * The questions of the wizard step, validated per DATA-016, or a
     * ValidationException with the errors keyed questions.<index>.<field>.
     *
     * @param  array<string, QuizQuestion>  $placed
     * @param  list<mixed>  $items
     * @return list<array{id: string, type: QuestionType, prompt: string, points: string, options: list<array{id: string|null, text: string, is_correct: bool}>, pairs: list<array{id: string|null, left_text: string, right_text: string}>}>
     *
     * @throws ValidationException
     */
    private function validate(Quiz $quiz, array $placed, array $items): array
    {
        $errors = [];
        $questions = [];
        $seen = [];

        foreach ($items as $index => $item) {
            $item = is_array($item) ? $item : [];
            $validator = Validator::make(
                $item,
                ['id' => ['required', 'uuid'], ...QuestionRules::rules($item['type'] ?? null)],
                QuestionRules::messages(),
                QuestionRules::attributes(),
            );

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors["questions.{$index}.{$field}"] = $messages;
                }

                continue;
            }

            /** @var array<string, mixed> $validated */
            $validated = $validator->validated();
            $question = ['id' => (string) $validated['id'], ...QuestionRules::normalize($validated)];

            if (isset($seen[$question['id']])) {
                $errors["questions.{$index}.id"] = [(string) __('assessment.quiz.errors.duplicate_question')];
            }

            if (! $quiz->mode->allowsQuestionType($question['type'])) {
                $errors["questions.{$index}.type"] = [(string) __('assessment.quiz.errors.live_free_text')];
            }

            $seen[$question['id']] = true;
            $questions[$index] = $question;
        }

        $errors = [...$errors, ...$this->foreignIds($placed, $questions)];

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_values($questions);
    }

    /**
     * Errors for ids of new questions, options and pairs that already
     * belong to another row: only the quiz's own rows may be addressed.
     *
     * @param  array<string, QuizQuestion>  $placed
     * @param  array<int, array{id: string, options: list<array{id: string|null}>, pairs: list<array{id: string|null}>}>  $questions
     * @return array<string, list<string>>
     */
    private function foreignIds(array $placed, array $questions): array
    {
        $candidates = ['questions' => [], 'options' => [], 'pairs' => []];
        $errors = [];
        $seen = [];

        foreach ($questions as $index => $question) {
            $own = $placed[$question['id']]->question ?? null;

            if ($own === null) {
                $candidates['questions'][$question['id']] = "questions.{$index}.id";
            }

            foreach (['options', 'pairs'] as $kind) {
                $ownIds = $own === null ? [] : $own->{$kind}->modelKeys();

                foreach ($question[$kind] as $position => $item) {
                    if ($item['id'] === null) {
                        continue;
                    }

                    $field = "questions.{$index}.{$kind}.{$position}.id";

                    // An option or pair belongs to one question only.
                    if (isset($seen[$kind][$item['id']])) {
                        $errors[$field] = [(string) __('assessment.quiz.errors.foreign_id')];
                    }

                    $seen[$kind][$item['id']] = true;

                    if (! in_array($item['id'], $ownIds, true)) {
                        $candidates[$kind][$item['id']] = $field;
                    }
                }
            }
        }

        $taken = [
            ...$this->existing(Question::class, array_keys($candidates['questions'])),
            ...$this->existing(ChoiceOption::class, array_keys($candidates['options'])),
            ...$this->existing(MatchingPair::class, array_keys($candidates['pairs'])),
        ];

        foreach ($candidates as $fields) {
            foreach ($fields as $id => $field) {
                if (isset($taken[$id])) {
                    $errors[$field] = [(string) __('assessment.quiz.errors.foreign_id')];
                }
            }
        }

        return $errors;
    }

    /**
     * Which of the ids exist, in any tenant (a primary key is global).
     *
     * @param  class-string<Model>  $model
     * @param  list<string|int>  $ids
     * @return array<string, true>
     */
    private function existing(string $model, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_fill_keys(
            $model::query()->withoutGlobalScopes()->whereKey(array_map('strval', $ids))->pluck('id')->map(fn (mixed $id): string => (string) $id)->all(),
            true,
        );
    }

    /**
     * Stores the options or pairs in the given order (position = index):
     * an item with the id of one of the question's own keeps that row, an
     * item with a new id is created with it, and rows left out are deleted.
     *
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
            $model = $item['id'] !== null && isset($existing[$item['id']]) ? $existing[$item['id']] : $relation->make();

            if (! $model->exists && $item['id'] !== null) {
                $model->setAttribute('id', $item['id']);
            }

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

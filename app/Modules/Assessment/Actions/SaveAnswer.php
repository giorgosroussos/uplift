<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\QuestionType;
use App\Modules\Assessment\Exceptions\AttemptClosed;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\AnswerMatch;
use App\Modules\Assessment\Models\AnswerSelection;
use App\Modules\Assessment\Models\ChoiceOption;
use App\Modules\Assessment\Models\MatchingPair;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Assessment\Support\MatchingItems;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * UC-012 step 2: autosaves the answer to one question of an in-progress
 * attempt (replacing what was saved before): the selected options, the
 * matches (left pair id => opaque right id, see MatchingItems) or the
 * text. The answer stays pending until submission (SCOPE-023).
 *
 * The time limit is enforced on the server (DATA-004): an answer that
 * arrives once the attempt's time is up is not counted; the attempt is
 * submitted with the answers saved before.
 */
class SaveAnswer
{
    public const int MAX_TEXT_LENGTH = 10000;

    public function __construct(private readonly SubmitQuizAttempt $submit) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AttemptClosed when the attempt is no longer in progress
     * @throws ValidationException when the answer does not fit the question
     */
    public function handle(QuizAttempt $attempt, QuizQuestion $placement, array $input): Answer
    {
        if ($this->submit->submitIfDue($attempt)) {
            throw AttemptClosed::make();
        }

        $answer = DB::transaction(function () use ($attempt, $placement, $input): ?Answer {
            $locked = QuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($locked->status !== AttemptStatus::InProgress || $locked->isDue()) {
                return null;
            }

            $question = $placement->question()->with(['options', 'pairs'])->firstOrFail();

            $answer = Answer::query()
                ->where('attempt_id', $locked->id)
                ->where('quiz_question_id', $placement->id)
                ->first() ?? new Answer;

            if (! $answer->exists) {
                $answer->organization_id = $locked->organization_id;
                $answer->attempt_id = $locked->id;
                $answer->quiz_question_id = $placement->id;
            }

            match (true) {
                $question->type->hasOptions() => $this->saveSelections($answer, $question->type, $question->options->all(), $input),
                $question->type->hasPairs() => $this->saveMatches($answer, $locked, $question, $input),
                default => $this->saveText($answer, $input),
            };

            return $answer;
        });

        if ($answer === null) {
            // A due attempt reached here only through a race with the clock.
            $this->submit->submitIfDue($attempt);

            throw AttemptClosed::make();
        }

        return $answer;
    }

    /**
     * @param  list<ChoiceOption>  $options
     * @param  array<string, mixed>  $input
     */
    private function saveSelections(Answer $answer, QuestionType $type, array $options, array $input): void
    {
        $ids = array_map(fn (ChoiceOption $o): string => $o->id, $options);

        $data = Validator::make($input, [
            'option_ids' => ['present', 'array', 'list', ...($type->hasSingleCorrectOption() ? ['max:1'] : [])],
            'option_ids.*' => ['string', 'distinct', Rule::in($ids)],
        ], $this->messages())->validate();

        $answer->save();
        $answer->selections()->delete();

        foreach ($data['option_ids'] as $id) {
            $selection = new AnswerSelection;
            $selection->organization_id = $answer->organization_id;
            $selection->answer_id = $answer->id;
            $selection->choice_option_id = $id;
            $selection->save();
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function saveMatches(Answer $answer, QuizAttempt $attempt, Question $question, array $input): void
    {
        $pairs = $question->pairs->all();
        $rightPairIds = MatchingItems::rightPairIds($attempt, $question);

        $data = Validator::make($input, [
            'matches' => ['present', 'array', 'list', 'max:'.count($pairs)],
            'matches.*' => ['array:left_id,right_id'],
            'matches.*.left_id' => ['required', 'string', 'distinct', Rule::in(array_map(fn (MatchingPair $p): string => $p->id, $pairs))],
            'matches.*.right_id' => ['required', 'string', Rule::in(array_keys($rightPairIds))],
        ], $this->messages())->validate();

        $answer->save();
        $answer->matches()->delete();

        foreach ($data['matches'] as $match) {
            $row = new AnswerMatch;
            $row->organization_id = $answer->organization_id;
            $row->answer_id = $answer->id;
            $row->left_pair_id = $match['left_id'];
            $row->right_pair_id = $rightPairIds[$match['right_id']];
            $row->save();
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function saveText(Answer $answer, array $input): void
    {
        $data = Validator::make($input, [
            'text' => ['present', 'nullable', 'string', 'max:'.self::MAX_TEXT_LENGTH],
        ], $this->messages())->validate();

        $text = $data['text'];
        $answer->text_response = is_string($text) && trim($text) !== '' ? $text : null;
        $answer->save();
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'option_ids.max' => (string) __('assessment.attempt.errors.one_option'),
            'in' => (string) __('assessment.attempt.errors.foreign_item'),
            'distinct' => (string) __('assessment.attempt.errors.duplicate_item'),
        ];
    }
}

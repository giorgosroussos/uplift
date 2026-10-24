<?php

namespace App\Modules\Live\Actions;

use App\Modules\Assessment\Models\ChoiceOption;
use App\Modules\Assessment\Models\MatchingPair;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Live\Events\LiveProgressUpdated;
use App\Modules\Live\Exceptions\LiveSessionClosed;
use App\Modules\Live\Models\LiveAnswer;
use App\Modules\Live\Models\LiveParticipant;
use App\Modules\Live\Models\LiveSession;
use App\Modules\Live\Support\LiveMatchingItems;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * UC-025 step 2: saves the participant's current answer to one question,
 * replacing the one saved before (moving freely between questions): the
 * selected options (`option_ids`) or the matches (`matches`, left pair id
 * => opaque right id, see LiveMatchingItems). Submitted over HTTP, never
 * through websocket client events (ARCH-006). Nothing about correctness is
 * returned (SCOPE-021).
 *
 * The common end time is enforced on the server: an answer that arrives
 * once it has passed, after the participant submitted or while the
 * session is not running is refused and not counted (LiveSessionClosed);
 * a session whose end time has passed is finished on the way.
 */
class SaveLiveAnswer
{
    public function __construct(private readonly FinishLiveSession $finish) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws LiveSessionClosed when the participation takes no answers
     * @throws ValidationException when the answer does not fit the question
     */
    public function handle(LiveParticipant $participant, QuizQuestion $placement, array $input): LiveAnswer
    {
        $answer = DB::transaction(function () use ($participant, $placement, $input): ?LiveAnswer {
            // A shared lock: answers of many students do not wait for each
            // other, but never interleave with the finish (row lock).
            $session = LiveSession::query()->sharedLock()->findOrFail($participant->live_session_id);
            $locked = LiveParticipant::query()->lockForUpdate()->findOrFail($participant->id);
            $now = CarbonImmutable::now();

            // Only questions of the session's quiz.
            abort_unless($placement->quiz_id === $session->quiz_id, 404);

            if (! $session->takesAnswers($now) || $locked->hasSubmitted()) {
                return null;
            }

            $question = $placement->question()->with(['options', 'pairs'])->firstOrFail();

            $answer = LiveAnswer::query()
                ->where('live_participant_id', $locked->id)
                ->where('quiz_question_id', $placement->id)
                ->first() ?? new LiveAnswer;

            if (! $answer->exists) {
                $answer->live_participant_id = $locked->id;
                $answer->quiz_question_id = $placement->id;
            }

            $answer->response = match (true) {
                $question->type->hasOptions() => $this->selections($question, $input),
                $question->type->hasPairs() => $this->matches($locked, $question, $input),
                // Live quizzes have no free text (SCOPE-019).
                default => throw ValidationException::withMessages(['question' => __('live.errors.foreign_item')]),
            };
            $answer->answered_at = $now;
            $answer->save();

            // The host sees the new answered count (UC-024 step 4).
            $answered = LiveAnswer::query()->where('live_participant_id', $locked->id)->count();
            event(LiveProgressUpdated::of($locked, $answered));

            return $answer;
        });

        if ($answer === null) {
            $this->finishIfDue($participant);

            throw LiveSessionClosed::make();
        }

        return $answer;
    }

    private function finishIfDue(LiveParticipant $participant): void
    {
        $session = LiveSession::query()->find($participant->live_session_id);

        if ($session !== null && $session->isRunning() && $session->hasEnded()) {
            $this->finish->handle($session);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{option_ids: list<string>}
     */
    private function selections(Question $question, array $input): array
    {
        $ids = $question->options->map(fn (ChoiceOption $o): string => $o->id)->all();

        $data = Validator::make($input, [
            'option_ids' => ['present', 'array', 'list', ...($question->type->hasSingleCorrectOption() ? ['max:1'] : [])],
            'option_ids.*' => ['string', 'distinct', Rule::in($ids)],
        ], $this->messages())->validate();

        /** @var list<string> $selected */
        $selected = array_values($data['option_ids']);

        return ['option_ids' => $selected];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{matches: list<array{left_pair_id: string, right_pair_id: string}>}
     */
    private function matches(LiveParticipant $participant, Question $question, array $input): array
    {
        $pairs = $question->pairs->all();
        $rightPairIds = LiveMatchingItems::rightPairIds($participant, $question);

        $data = Validator::make($input, [
            'matches' => ['present', 'array', 'list', 'max:'.count($pairs)],
            'matches.*' => ['array:left_id,right_id'],
            'matches.*.left_id' => ['required', 'string', 'distinct', Rule::in(array_map(fn (MatchingPair $p): string => $p->id, $pairs))],
            'matches.*.right_id' => ['required', 'string', Rule::in(array_keys($rightPairIds))],
        ], $this->messages())->validate();

        $matches = [];

        foreach ($data['matches'] as $match) {
            $matches[] = [
                'left_pair_id' => (string) $match['left_id'],
                'right_pair_id' => $rightPairIds[$match['right_id']],
            ];
        }

        return ['matches' => $matches];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'option_ids.max' => (string) __('live.errors.one_option'),
            'in' => (string) __('live.errors.foreign_item'),
            'distinct' => (string) __('live.errors.duplicate_item'),
        ];
    }
}

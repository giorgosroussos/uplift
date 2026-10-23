<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Poll;
use App\Modules\Activities\Models\PollParticipation;
use App\Modules\Activities\Models\PollVote;
use App\Modules\Identity\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * UC-013 steps 2-3: a student votes in an open poll with one option, or
 * up to the allowed number of options. The vote is final: a second vote
 * is refused (SCOPE-013), and so is a vote in a closed poll.
 *
 * The participation (THAT the student voted) and the votes (WHAT was
 * chosen, without any user reference) are written in one transaction but
 * never linked (DATA-006). The poll row is locked so concurrent votes of
 * the same student are serialised; the unique participation is the
 * backstop.
 */
class CastPollVote
{
    /**
     * @param  array<string, mixed>  $input  {options: list<option id>}
     *
     * @throws ValidationException (field "options") when the vote is refused
     */
    public function handle(User $student, Poll $poll, array $input): void
    {
        /** @var array{options: list<string>} $data */
        $data = Validator::make($input, [
            'options' => ['required', 'array', 'list', 'min:1'],
            'options.*' => ['required', 'uuid', 'distinct'],
        ], [
            'options.required' => (string) __('activities.poll.vote.errors.choose'),
            'options.min' => (string) __('activities.poll.vote.errors.choose'),
        ], [
            'options' => (string) __('activities.poll.attributes.options'),
            'options.*' => (string) __('activities.poll.attributes.option'),
        ])->validate();

        try {
            DB::transaction(function () use ($student, $poll, $data): void {
                // Serialises the vote against closing the activity and against other votes.
                Activity::query()->lockForUpdate()->findOrFail($poll->activity_id);
                $locked = Poll::query()->lockForUpdate()->findOrFail($poll->activity_id);

                if (! $locked->isOpen()) {
                    $this->refuse('closed');
                }

                if ($locked->participations()->where('student_id', $student->getKey())->exists()) {
                    $this->refuse('already_voted');
                }

                $chosen = array_values(array_unique($data['options']));

                if (count($chosen) > $locked->max_choices) {
                    $this->refuse('too_many', ['max' => $locked->max_choices]);
                }

                $options = $locked->options()->whereKey($chosen)->pluck('id')->all();

                if (count($options) !== count($chosen)) {
                    $this->refuse('unknown_option');
                }

                $participation = new PollParticipation;
                $participation->organization_id = $locked->organization_id;
                $participation->poll_id = $locked->activity_id;
                $participation->student_id = (string) $student->getKey();
                $participation->save();

                foreach ($options as $optionId) {
                    $vote = new PollVote;
                    $vote->organization_id = $locked->organization_id;
                    $vote->poll_option_id = (string) $optionId;
                    $vote->save();
                }
            });
        } catch (UniqueConstraintViolationException) {
            $this->refuse('already_voted');
        }
    }

    /**
     * @param  array<string, int|string>  $replace
     */
    private function refuse(string $reason, array $replace = []): never
    {
        throw ValidationException::withMessages([
            'options' => (string) __("activities.poll.vote.refused.{$reason}", $replace),
        ]);
    }
}

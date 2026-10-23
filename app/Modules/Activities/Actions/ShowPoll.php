<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Models\Poll;
use App\Modules\Activities\Models\PollOption;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * UC-013: a poll as its viewer may see it. A student sees the options,
 * whether they have voted themselves (their own participation, OWN-011)
 * and the results as the teacher set them (SCOPE-013); the owning teacher
 * sees the results at any time. Results are the number of votes per option
 * and the number of participants only, never who voted or what anyone
 * voted (SEC-009).
 */
class ShowPoll
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, Poll $poll): array
    {
        $activity = $poll->activity;
        $gate = Gate::forUser($user);
        $hasVoted = $poll->participations()->where('student_id', $user->getKey())->exists();

        return [
            'poll' => [
                'activity_id' => $activity->id,
                'title' => $activity->title,
                'description' => $activity->description,
                'status' => $activity->status->value,
                'course_space_title' => $activity->courseSpace->title,
                'deadline_at' => $activity->deadline_at?->toIso8601String(),
                'max_choices' => $poll->max_choices,
                'results_visibility' => $poll->results_visibility->value,
                'is_open' => $poll->isOpen(),
                'options' => $poll->options()->get()
                    ->map(fn (PollOption $option): array => ['id' => $option->id, 'label' => $option->label])
                    ->values()
                    ->all(),
            ],
            'hasVoted' => $hasVoted,
            'results' => $gate->allows('viewResults', $poll) ? self::results($poll) : null,
            'can' => [
                'vote' => $gate->allows('vote', $poll) && $poll->isOpen() && ! $hasVoted,
                'update' => $gate->allows('update', $activity),
            ],
        ];
    }

    /**
     * The tally: votes per option in the poll's order and the number of
     * participants.
     *
     * @return array{participants: int, options: list<array{id: string, label: string, votes: int}>}
     */
    public static function results(Poll $poll): array
    {
        return [
            'participants' => $poll->participations()->count(),
            'options' => $poll->options()->withCount('votes')->get()
                ->map(fn (PollOption $option): array => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'votes' => (int) $option->getAttribute('votes_count'),
                ])
                ->values()
                ->all(),
        ];
    }
}

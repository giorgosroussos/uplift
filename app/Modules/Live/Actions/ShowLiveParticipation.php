<?php

namespace App\Modules\Live\Actions;

use App\Modules\Assessment\Models\Question;
use App\Modules\Live\Models\LiveAnswer;
use App\Modules\Live\Models\LiveParticipant;
use App\Modules\Live\Models\LiveSession;
use App\Modules\Live\Support\LiveMatchingItems;
use Carbon\CarbonImmutable;

/**
 * UC-025: what a student sees of their own participation (OWN-012,
 * OWN-013): the session's status and common end time with the server's
 * clock (ARCH-006), so a late or reconnecting student has exactly the time
 * left; their saved answers; once the session has finished, which of them
 * were correct, their points, score and, when leaderboards are enabled,
 * their final rank. Before the finish nothing tells correctness
 * (SCOPE-021). The caller authorises (LiveParticipantPolicy::view).
 */
class ShowLiveParticipation
{
    /**
     * @return array{
     *     session: array{id: string, quiz_id: string, status: string, started_at: string|null, ends_at: string|null, server_now: string, remaining_seconds: int|null},
     *     participant: array{id: string, joined_at: string, submitted: bool, score: string|null, final_rank: int|null},
     *     answers: list<array<string, mixed>>
     * }
     */
    public function handle(LiveParticipant $participant): array
    {
        /** @var LiveSession $session */
        $session = LiveSession::query()->findOrFail($participant->live_session_id);
        $finished = $session->isFinished();
        $now = CarbonImmutable::now();

        $answers = LiveAnswer::query()
            ->where('live_participant_id', $participant->id)
            ->with('quizQuestion.question.pairs')
            ->get()
            ->map(fn (LiveAnswer $answer): array => [
                'quiz_question_id' => $answer->quiz_question_id,
                'response' => $this->response($participant, $answer),
                ...($finished ? ['is_correct' => $answer->is_correct, 'points' => $answer->points] : []),
            ])
            ->values()
            ->all();

        return [
            'session' => [
                'id' => $session->id,
                'quiz_id' => $session->quiz_id,
                'status' => $session->status->value,
                'started_at' => $session->started_at?->toIso8601String(),
                'ends_at' => $session->ends_at?->toIso8601String(),
                'server_now' => $now->toIso8601String(),
                'remaining_seconds' => $session->isRunning() ? $session->remainingSeconds($now) : null,
            ],
            'participant' => [
                'id' => $participant->id,
                'joined_at' => $participant->joined_at->toIso8601String(),
                'submitted' => $participant->hasSubmitted(),
                'score' => $finished ? $participant->score : null,
                'final_rank' => $finished && ShowLiveResults::leaderboardEnabled($session) ? $participant->final_rank : null,
            ],
            'answers' => $answers,
        ];
    }

    /**
     * The answer as the student gave it: option ids, or matches with the
     * student's opaque right ids.
     *
     * @return array<string, mixed>
     */
    private function response(LiveParticipant $participant, LiveAnswer $answer): array
    {
        if (! array_key_exists('matches', $answer->response)) {
            return ['option_ids' => array_values((array) ($answer->response['option_ids'] ?? []))];
        }

        /** @var Question $question */
        $question = $answer->quizQuestion->question;
        $known = $question->pairs->pluck('id')->all();
        $matches = [];

        foreach ((array) $answer->response['matches'] as $match) {
            if (is_array($match) && in_array($match['right_pair_id'] ?? null, $known, true)) {
                $matches[] = [
                    'left_id' => (string) $match['left_pair_id'],
                    'right_id' => LiveMatchingItems::rightId($participant, (string) $match['right_pair_id']),
                ];
            }
        }

        return ['matches' => $matches];
    }
}

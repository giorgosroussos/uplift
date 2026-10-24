<?php

namespace App\Modules\Live\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Live\Models\LiveAnswer;
use App\Modules\Live\Models\LiveParticipant;
use App\Modules\Live\Models\LiveSession;
use App\Modules\Tenancy\Models\Organization;

/**
 * UC-024 step 5 / UC-025 step 4: the outcome of a finished session.
 *
 * - With leaderboards enabled for the organisation: the final ranking and
 *   the podium (ranks 1 to 3) of this session only, showing nickname and
 *   avatar only (SEC-008), to the host and to participants.
 * - With leaderboards disabled: no ranking and no podium for anyone
 *   (UC-024); each student sees only their own score.
 * - The host also sees every participant's score and, per question, how
 *   many answered it and how many got it right (OWN-012, OWN-013); a
 *   student sees their own score.
 *
 * Null while the session has not finished. The caller authorises: the
 * host (LiveSessionPolicy::host) or a participant (pass it as $own).
 */
class ShowLiveResults
{
    public const int PODIUM_PLACES = 3;

    /**
     * @return array<string, mixed>|null
     */
    public function handle(LiveSession $session, ?LiveParticipant $own = null): ?array
    {
        if (! $session->isFinished()) {
            return null;
        }

        $participants = LiveParticipant::query()
            ->where('live_session_id', $session->id)
            ->with('student')
            ->get();

        $leaderboard = self::leaderboardEnabled($session);
        $ranking = $leaderboard ? $this->ranking($participants->all()) : null;

        $result = [
            'leaderboard' => $leaderboard,
            'ranking' => $ranking,
            'podium' => $ranking === null ? null : array_values(array_filter(
                $ranking,
                fn (array $entry): bool => $entry['rank'] <= self::PODIUM_PLACES,
            )),
        ];

        if ($own !== null) {
            return [...$result, 'own' => [
                'score' => $own->score,
                'final_rank' => $leaderboard ? $own->final_rank : null,
            ]];
        }

        return [
            ...$result,
            'participants' => $participants
                ->sortBy(fn (LiveParticipant $p): string => $leaderboard
                    ? sprintf('%08d|%s', $p->final_rank ?? 0, $p->student->nickname)
                    : $p->student->nickname)
                ->map(fn (LiveParticipant $p): array => [
                    'id' => $p->id,
                    'student' => $p->student->publicProfile(),
                    'score' => $p->score,
                    'final_rank' => $leaderboard ? $p->final_rank : null,
                ])
                ->values()
                ->all(),
            'questions' => $this->questions($session),
        ];
    }

    public static function leaderboardEnabled(LiveSession $session): bool
    {
        return (bool) Organization::query()->whereKey($session->organization_id)->value('leaderboard_enabled');
    }

    /**
     * @param  list<LiveParticipant>  $participants
     * @return list<array{rank: int, student: array{id: string, nickname: string, avatar: string|null}}>
     */
    private function ranking(array $participants): array
    {
        usort($participants, fn (LiveParticipant $a, LiveParticipant $b): int => [$a->final_rank, $a->student->nickname] <=> [$b->final_rank, $b->student->nickname]);

        return array_map(fn (LiveParticipant $p): array => [
            'rank' => (int) $p->final_rank,
            'student' => $p->student->publicProfile(),
        ], $participants);
    }

    /**
     * Per question: how many participants answered it and how many earned
     * its full points.
     *
     * @return list<array{quiz_question_id: string, answered: int, correct: int}>
     */
    private function questions(LiveSession $session): array
    {
        return LiveAnswer::query()
            ->whereIn('live_participant_id', LiveParticipant::query()->where('live_session_id', $session->id)->select('id'))
            ->selectRaw('quiz_question_id, count(*) as answered, count(*) filter (where is_correct) as correct')
            ->groupBy('quiz_question_id')
            ->orderBy('quiz_question_id')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'quiz_question_id' => (string) $row->quiz_question_id,
                'answered' => (int) $row->answered,
                'correct' => (int) $row->correct,
            ])
            ->values()
            ->all();
    }

    /**
     * The viewer's own participation in the session, if any.
     */
    public static function participationOf(LiveSession $session, User $viewer): ?LiveParticipant
    {
        return LiveParticipant::query()
            ->where('live_session_id', $session->id)
            ->where('student_id', $viewer->getKey())
            ->first();
    }
}

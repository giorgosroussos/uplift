<?php

namespace App\Modules\Live\Actions;

use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Live\Models\LiveParticipant;
use App\Modules\Live\Models\LiveSession;
use Carbon\CarbonImmutable;

/**
 * UC-024 step 2 and 4: what the host teacher sees of a session (OWN-012,
 * OWN-013): who joined (nickname and avatar, SEC-008), how many questions
 * each participant has answered and who has submitted, never an answer
 * or its correctness. Read straight from PostgreSQL, so every saved
 * answer shows on the next read. Countdowns derive from ends_at and
 * server_now (ARCH-006). The caller authorises (LiveSessionPolicy::host).
 * The same participant row is broadcast to the host (LiveProgressUpdated).
 *
 * @phpstan-type ProgressRow array{id: string, student: array{id: string, nickname: string, avatar: string|null}, joined_at: string, answered: int, submitted: bool}
 */
class ShowLiveProgress
{
    /**
     * @return array{
     *     session: array{id: string, quiz_id: string, status: string, started_at: string|null, ends_at: string|null, server_now: string, question_count: int},
     *     participants: list<ProgressRow>
     * }
     */
    public function handle(LiveSession $session): array
    {
        $participants = LiveParticipant::query()
            ->where('live_session_id', $session->id)
            ->with('student')
            ->withCount('answers')
            ->orderBy('joined_at')
            ->orderBy('id')
            ->get()
            ->map(fn (LiveParticipant $p): array => self::row($p, (int) $p->getAttribute('answers_count')))
            ->values()
            ->all();

        return [
            'session' => [
                'id' => $session->id,
                'quiz_id' => $session->quiz_id,
                'status' => $session->status->value,
                'started_at' => $session->started_at?->toIso8601String(),
                'ends_at' => $session->ends_at?->toIso8601String(),
                'server_now' => CarbonImmutable::now()->toIso8601String(),
                'question_count' => QuizQuestion::query()->where('quiz_id', $session->quiz_id)->count(),
            ],
            'participants' => $participants,
        ];
    }

    /**
     * One participant's progress: nickname and avatar only (SEC-008), the
     * number of questions answered and whether they submitted.
     *
     * @return ProgressRow
     */
    public static function row(LiveParticipant $participant, int $answered): array
    {
        return [
            'id' => $participant->id,
            'student' => $participant->student->publicProfile(),
            'joined_at' => $participant->joined_at->toIso8601String(),
            'answered' => $answered,
            'submitted' => $participant->hasSubmitted(),
        ];
    }
}

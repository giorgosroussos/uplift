<?php

namespace App\Modules\Live\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Identity\Models\User;
use App\Modules\Live\Models\LiveSession;
use Illuminate\Support\Facades\Gate;

/**
 * UC-024: the teacher's monitor page of a live quiz. The quiz (start mode,
 * scheduled start, duration), its questions in order (to label the
 * per-question results) and its current session, the non-cancelled one
 * (SCOPE-022): none before a lobby is opened or after it was closed
 * without starting, else the lobby, the running or the finished session.
 *
 * Only the session's host (LiveSessionPolicy::host) gets its state
 * snapshot (ShowLiveHostState: channels, progress, results; OWN-012,
 * OWN-013); any other teacher of the course space sees only that the
 * session exists. Null when the activity is not a live quiz. The caller
 * authorises (LiveSessionPolicy::open).
 */
class ShowLiveMonitor
{
    public function __construct(private readonly ShowLiveHostState $state) {}

    /**
     * @return array<string, mixed>|null
     */
    public function handle(User $teacher, Activity $activity): ?array
    {
        $quiz = Quiz::forActivity($activity);

        if ($quiz === null || ! $quiz->isLive()) {
            return null;
        }

        $session = OpenLiveLobby::current($activity->id);
        $hosting = $session !== null && Gate::forUser($teacher)->allows('host', $session);

        return [
            'activity' => [
                'id' => $activity->id,
                'title' => $activity->title,
                'status' => $activity->status->value,
                'course_space_title' => $activity->courseSpace->title,
            ],
            'quiz' => [
                'start_mode' => $quiz->live_start_mode?->value,
                'scheduled_start_at' => $quiz->live_scheduled_start_at?->toIso8601String(),
                'duration_seconds' => (int) $quiz->time_limit,
            ],
            'questions' => $this->questions($quiz),
            'session' => $session === null ? null : $this->session($session, $hosting),
            'state' => $hosting ? $this->state->handle($session) : null,
        ];
    }

    /**
     * @return array{id: string, status: string, hosting: bool}
     */
    private function session(LiveSession $session, bool $hosting): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status->value,
            'hosting' => $hosting,
        ];
    }

    /**
     * @return list<array{id: string, position: int, prompt: string, points: string}>
     */
    private function questions(Quiz $quiz): array
    {
        return QuizQuestion::query()
            ->where('quiz_id', $quiz->activity_id)
            ->with('question')
            ->orderBy('position')
            ->get()
            ->map(fn (QuizQuestion $quizQuestion): array => [
                'id' => $quizQuestion->id,
                'position' => $quizQuestion->position,
                'prompt' => $quizQuestion->question->prompt,
                'points' => $quizQuestion->question->points,
            ])
            ->values()
            ->all();
    }
}

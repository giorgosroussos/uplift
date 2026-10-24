<?php

namespace App\Modules\Live\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\LiveStartMode;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Identity\Models\User;
use App\Modules\Live\Models\LiveParticipant;
use App\Modules\Live\Models\LiveSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-025 step 1: an enrolled student (LiveSessionPolicy::join, SEC-005)
 * joins the live quiz's session: in the lobby to wait for the start, or
 * while it runs with only the time left until the common end time
 * (SCOPE-021). Joining again continues the same participation (reconnect).
 * A scheduled quiz gets its lobby on the first join, hosted by the course
 * space's teacher; a manual quiz waits for its host to open one. A
 * finished session, or one whose end time has passed, takes no one.
 */
class JoinLiveQuiz
{
    /**
     * @throws ValidationException (field "session")
     */
    public function handle(User $student, Activity $activity): LiveParticipant
    {
        return DB::transaction(function () use ($student, $activity): LiveParticipant {
            // Lock order: quiz, then session (as OpenLiveLobby and StartLiveSession).
            $quiz = Quiz::query()->lockForUpdate()->find($activity->id);
            OpenLiveLobby::ensureRunnable($activity, $quiz);

            /** @var Quiz $quiz */
            $session = $this->session($quiz, $activity);
            $session = LiveSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($session->isFinished() || ($session->isRunning() && $session->hasEnded())) {
                throw OpenLiveLobby::refused('closed');
            }

            $participant = LiveParticipant::query()
                ->where('live_session_id', $session->id)
                ->where('student_id', $student->getKey())
                ->first();

            if ($participant !== null) {
                return $participant;
            }

            $participant = new LiveParticipant;
            $participant->live_session_id = $session->id;
            $participant->student_id = (string) $student->getKey();
            $participant->joined_at = CarbonImmutable::now();
            $participant->save();

            return $participant;
        });
    }

    /**
     * @throws ValidationException
     */
    private function session(Quiz $quiz, Activity $activity): LiveSession
    {
        $current = OpenLiveLobby::current($quiz->activity_id);

        if ($current !== null) {
            return $current;
        }

        if ($quiz->live_start_mode !== LiveStartMode::Scheduled) {
            throw OpenLiveLobby::refused('not_open');
        }

        $session = new LiveSession;
        $session->quiz_id = $quiz->activity_id;
        $session->host_id = $activity->courseSpace->teacher_id;
        $session->save();

        return $session;
    }
}

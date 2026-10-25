<?php

namespace App\Modules\Live\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\LiveStartMode;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Live\Enums\LiveSessionStatus;
use App\Modules\Live\Events\LiveSessionChanged;
use App\Modules\Live\Events\LiveSessionStarted;
use App\Modules\Live\Jobs\FinishLiveSessionJob;
use App\Modules\Live\Models\LiveSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-024 step 2-3: starts a lobby (lobby -> running) and fixes the one
 * common end time for every participant: start time + quiz duration
 * (SCOPE-021, server time). A manual quiz is started by its host
 * (handle); a scheduled one by the scheduler at its start time (begin,
 * StartScheduledLiveSessions). The finish is queued for the end time;
 * FinishDueLiveSessions catches it should the queue be late.
 */
class StartLiveSession
{
    /**
     * The host presses Start (manual start mode only).
     *
     * @throws ValidationException (field "session")
     */
    public function handle(LiveSession $session): LiveSession
    {
        return $this->begin($session, LiveStartMode::Manual);
    }

    /**
     * @param  LiveStartMode|null  $mode  the start mode the quiz must have, null for any
     *
     * @throws ValidationException (field "session")
     */
    public function begin(LiveSession $session, ?LiveStartMode $mode = null): LiveSession
    {
        $started = DB::transaction(function () use ($session, $mode): LiveSession {
            // Lock order: quiz, then session (as OpenLiveLobby and JoinLiveQuiz).
            $quiz = Quiz::query()->lockForUpdate()->find($session->quiz_id);
            $locked = LiveSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $locked->isLobby()) {
                throw OpenLiveLobby::refused($locked->status->hasStarted() ? 'already_run' : 'not_open');
            }

            OpenLiveLobby::ensureRunnable(Activity::query()->find($locked->quiz_id), $quiz);

            /** @var Quiz $quiz */
            if ($mode !== null && $quiz->live_start_mode !== $mode) {
                throw OpenLiveLobby::refused('scheduled_start');
            }

            $now = CarbonImmutable::now();

            $locked->transitionTo(LiveSessionStatus::Running);
            $locked->started_at = $now;
            $locked->ends_at = $now->addSeconds((int) $quiz->time_limit);
            $locked->save();

            LiveSessionStarted::dispatch($locked->organization_id, $locked->id, $locked->quiz_id);
            event(LiveSessionChanged::of($locked));

            return $locked;
        });

        /** @var CarbonImmutable $endsAt */
        $endsAt = $started->ends_at;

        FinishLiveSessionJob::dispatch($started->organization_id, $started->id)->delay($endsAt)->afterCommit();

        return $started;
    }
}

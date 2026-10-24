<?php

namespace App\Modules\Live\Actions;

use App\Modules\Live\Models\LiveParticipant;
use App\Modules\Live\Models\LiveSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-025 step 3: the participant submits early; their answers can no
 * longer change. When every joined student has submitted, the session
 * finishes at once (UC-024 step 5). A participation that is already
 * submitted, or whose session is not running or has passed its end time,
 * is refused; the latter is then finished.
 */
class SubmitLiveParticipation
{
    public function __construct(private readonly FinishLiveSession $finish) {}

    /**
     * @throws ValidationException (field "session") when the participation takes no answers
     */
    public function handle(LiveParticipant $participant): LiveParticipant
    {
        $submitted = DB::transaction(function () use ($participant): ?LiveParticipant {
            $session = LiveSession::query()->sharedLock()->findOrFail($participant->live_session_id);
            $locked = LiveParticipant::query()->lockForUpdate()->findOrFail($participant->id);
            $now = CarbonImmutable::now();

            if (! $session->takesAnswers($now) || $locked->hasSubmitted()) {
                return null;
            }

            $locked->submitted_at = $now;
            $locked->save();

            return $locked;
        });

        // Early finish when this was the last one, or the end time passed.
        $session = LiveSession::query()->findOrFail($participant->live_session_id);

        if ($session->isRunning()) {
            $this->finish->handle($session);
        }

        if ($submitted === null) {
            throw OpenLiveLobby::refused('closed');
        }

        return $submitted;
    }
}

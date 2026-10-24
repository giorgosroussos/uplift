<?php

namespace App\Modules\Live\Actions;

use App\Modules\Live\Enums\LiveSessionStatus;
use App\Modules\Live\Models\LiveSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-024 exception: the host closes the lobby without starting
 * (lobby -> cancelled). The quiz is not considered run; a new lobby may be
 * opened later (OpenLiveLobby). A started session cannot be cancelled.
 */
class CancelLiveLobby
{
    /**
     * @throws ValidationException (field "session")
     */
    public function handle(LiveSession $session): LiveSession
    {
        return DB::transaction(function () use ($session): LiveSession {
            $locked = LiveSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $locked->isLobby()) {
                throw OpenLiveLobby::refused($locked->status->hasStarted() ? 'already_run' : 'not_open');
            }

            $locked->transitionTo(LiveSessionStatus::Cancelled);
            $locked->save();

            return $locked;
        });
    }
}

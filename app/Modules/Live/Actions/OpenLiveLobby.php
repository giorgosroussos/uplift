<?php

namespace App\Modules\Live\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Identity\Models\User;
use App\Modules\Live\Enums\LiveSessionStatus;
use App\Modules\Live\Models\LiveSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-024 step 1-2: opens the lobby of a published live quiz, hosted by the
 * given teacher. At most one non-cancelled session per quiz (SCOPE-022):
 * an open lobby is returned as it is; a quiz that was run (running or
 * finished) is refused (SCOPE-019); after a lobby was closed without
 * starting a new one may be opened (UC-024). The quiz row is locked so
 * concurrent openings see the same state.
 */
class OpenLiveLobby
{
    /**
     * @throws ValidationException (field "session")
     */
    public function handle(User $host, Activity $activity): LiveSession
    {
        return DB::transaction(function () use ($host, $activity): LiveSession {
            $quiz = Quiz::query()->lockForUpdate()->find($activity->id);

            self::ensureRunnable($activity, $quiz);

            $current = self::current($activity->id);

            if ($current !== null) {
                if ($current->status->hasStarted()) {
                    throw self::refused('already_run');
                }

                return $current;
            }

            $session = new LiveSession;
            $session->quiz_id = $activity->id;
            $session->host_id = (string) $host->getKey();
            $session->save();

            return $session;
        });
    }

    /**
     * The quiz's non-cancelled session, if any.
     */
    public static function current(string $quizId): ?LiveSession
    {
        return LiveSession::query()
            ->where('quiz_id', $quizId)
            ->where('status', '<>', LiveSessionStatus::Cancelled->value)
            ->first();
    }

    /**
     * A live quiz runs only while its activity is published.
     *
     * @throws ValidationException
     */
    public static function ensureRunnable(?Activity $activity, ?Quiz $quiz): void
    {
        if ($quiz === null || ! $quiz->isLive()) {
            throw self::refused('not_live');
        }

        if ($activity === null || $activity->trashed() || ! $activity->isPublished()) {
            throw self::refused('not_published');
        }
    }

    public static function refused(string $reason): ValidationException
    {
        return ValidationException::withMessages(['session' => __("live.errors.{$reason}")]);
    }
}

<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Enums\ActivityStatus;
use App\Modules\Activities\Exceptions\InvalidActivityTransition;
use App\Modules\Activities\Models\Activity;
use Illuminate\Support\Facades\DB;

/**
 * Closes a published activity (published -> closed); a closed activity is
 * final and cannot be edited.
 */
class CloseActivity
{
    /**
     * @throws InvalidActivityTransition when the activity is not published
     */
    public function handle(Activity $activity): Activity
    {
        return DB::transaction(function () use ($activity): Activity {
            $locked = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            $locked->transitionTo(ActivityStatus::Closed);

            return $locked;
        });
    }
}

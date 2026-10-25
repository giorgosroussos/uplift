<?php

namespace App\Modules\Activities\Contracts;

use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\ActivityAction;
use App\Modules\Identity\Models\User;

/**
 * Optional contract an ActivityType may also implement to add buttons to
 * the activity page (ARCH-009), e.g. a link into the module that runs the
 * activity. Activities knows neither the routes nor the rules behind
 * them: the type decides which actions the given user gets, and the
 * activity page renders them in order (data-test activity-action-<key>).
 */
interface ProvidesActivityActions
{
    /**
     * The actions the user gets on the activity page, already authorised.
     *
     * @return list<ActivityAction>
     */
    public function actions(Activity $activity, User $user): array;
}

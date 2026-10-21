<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Enums\ActivityStatus;
use App\Modules\Activities\Events\ActivityPublished;
use App\Modules\Activities\Exceptions\InvalidActivityTransition;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Activities\Support\Basics;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-022 step 4: publishes a draft once every required field is valid
 * (the basics and whatever the type requires, e.g. a quiz needs at least
 * one question). A refused publish leaves the draft unchanged.
 */
class PublishActivity
{
    public function __construct(private readonly ActivityTypes $types) {}

    /**
     * @throws ValidationException when something keeps the activity from being published
     * @throws InvalidActivityTransition when the activity is no draft
     */
    public function handle(User $actor, Activity $activity): Activity
    {
        return DB::transaction(function () use ($actor, $activity): Activity {
            $locked = Activity::query()->lockForUpdate()->findOrFail($activity->id);

            $errors = [
                ...Basics::publishErrors($locked),
                ...$this->types->for($locked)->publishErrors($locked),
            ];

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $locked->transitionTo(ActivityStatus::Published);

            ActivityPublished::dispatch($locked->organization_id, $locked->course_space_id, $locked->id, (string) $actor->getKey());

            return $locked;
        });
    }
}

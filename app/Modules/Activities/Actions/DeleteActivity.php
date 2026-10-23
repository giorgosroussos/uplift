<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Events\ActivityDeleted;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-040 (SCOPE-029): soft-deletes a draft, or a published or closed
 * activity without submissions. Whether an activity has submissions is
 * decided by its type (ActivityType::hasSubmissions: any attempt, poll
 * participation, topic choice, proposal or current booking). The activity
 * row is locked, so the check and the delete see the same state. The
 * deletion cannot be undone in the UI and notifies nobody; ActivityDeleted
 * follows after commit.
 */
class DeleteActivity
{
    public function __construct(private readonly ActivityTypes $types) {}

    /**
     * @throws ValidationException (field "activity") when the activity has submissions
     */
    public function handle(User $actor, Activity $activity): void
    {
        DB::transaction(function () use ($actor, $activity): void {
            $locked = Activity::query()->lockForUpdate()->findOrFail($activity->id);

            if (! $locked->isDraft() && $this->hasSubmissions($locked)) {
                throw ValidationException::withMessages([
                    'activity' => __('activities.errors.has_submissions'),
                ]);
            }

            $locked->delete();

            ActivityDeleted::dispatch(
                $locked->organization_id,
                $locked->id,
                (string) $actor->getKey(),
                $locked->type->value,
            );
        });
    }

    /**
     * Whether a non-draft activity can no longer be deleted.
     */
    public function hasSubmissions(Activity $activity): bool
    {
        return $this->types->for($activity)->hasSubmissions($activity);
    }
}

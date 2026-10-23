<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Enums\WizardStep;
use App\Modules\Activities\Exceptions\GradingLocked;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Activities\Support\Basics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * UC-022: autosave of one wizard step. Basics are stored on the activity;
 * settings and content are validated and stored by the activity's type.
 * Whether the activity is a group activity, and its group size, cannot
 * change once it has submissions.
 * Once a published activity has submissions, a change the type reports as
 * changing grading is refused and nothing is stored.
 */
class SaveWizardStep
{
    public function __construct(private readonly ActivityTypes $types) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws GradingLocked
     */
    public function handle(Activity $activity, WizardStep $step, array $input): void
    {
        if ($step === WizardStep::Basics) {
            $basics = Basics::validate($input);

            DB::transaction(function () use ($activity, $basics): void {
                $locked = Activity::query()->lockForUpdate()->findOrFail($activity->id);
                $locked->fill($basics);

                // Who takes part (alone or as a group of up to group_size) is fixed once anyone has.
                if ($locked->isDirty(['is_group', 'group_size']) && $this->types->has($locked->type) && $this->types->for($locked)->hasSubmissions($locked)) {
                    throw GradingLocked::make();
                }

                $locked->save();
            });

            return;
        }

        if ($step === WizardStep::Review) {
            throw new InvalidArgumentException('The review step has nothing to save.');
        }

        $type = $this->types->for($activity);

        /** @var array<string, mixed> $data */
        $data = Validator::make($input, $type->rules($activity, $step))->validate();

        DB::transaction(function () use ($activity, $step, $type, $data): void {
            // Serialises saves against publishing and closing of the activity.
            $locked = Activity::query()->lockForUpdate()->findOrFail($activity->id);

            if ($type->hasSubmissions($locked) && $type->changesGrading($locked, $step, $data)) {
                throw GradingLocked::make();
            }

            $type->save($locked, $step, $data);
        });
    }
}

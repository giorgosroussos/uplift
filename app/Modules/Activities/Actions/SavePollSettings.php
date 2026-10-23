<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Enums\PollResultsVisibility;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Poll;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * UC-022 step 3 for a poll (wizard step "settings"): how many options a
 * student may choose and when students see the results (SCOPE-013).
 * Creates the poll subtype row on the first save. Called by the poll's
 * ActivityType inside the wizard's transaction.
 */
class SavePollSettings
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Activity $activity, array $input): Poll
    {
        $data = Validator::make($input, self::rules(), [], self::attributes())->validate();

        $poll = Poll::forActivity($activity) ?? new Poll;

        if (! $poll->exists) {
            $poll->activity_id = $activity->id;
            $poll->organization_id = $activity->organization_id;
        }

        $poll->fill([
            'max_choices' => (int) $data['max_choices'],
            'results_visibility' => PollResultsVisibility::from((string) $data['results_visibility']),
        ])->save();

        return $poll;
    }

    /**
     * The saved settings in the shape of the wizard step, or null when the
     * poll has no settings yet.
     *
     * @return array{max_choices: int, results_visibility: string}|null
     */
    public static function data(?Poll $poll): ?array
    {
        if ($poll === null) {
            return null;
        }

        return [
            'max_choices' => $poll->max_choices,
            'results_visibility' => $poll->results_visibility->value,
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private static function rules(): array
    {
        return [
            'max_choices' => ['required', 'integer', 'min:1', 'max:'.SavePollOptions::MAX_OPTIONS],
            'results_visibility' => ['required', Rule::enum(PollResultsVisibility::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function attributes(): array
    {
        return [
            'max_choices' => (string) __('activities.poll.settings.max_choices'),
            'results_visibility' => (string) __('activities.poll.settings.results_visibility'),
        ];
    }
}

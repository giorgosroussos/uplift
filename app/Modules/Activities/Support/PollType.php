<?php

namespace App\Modules\Activities\Support;

use App\Modules\Activities\Actions\SavePollOptions;
use App\Modules\Activities\Actions\SavePollSettings;
use App\Modules\Activities\Contracts\ActivityType;
use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\SubmissionStatus;
use App\Modules\Activities\Enums\WizardStep;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Poll;
use App\Modules\Activities\Models\PollParticipation;

/**
 * The poll activity type (ARCH-004, UC-013), registered with the
 * registry by ActivitiesServiceProvider. Its subtype row is ENT-Poll; the
 * wizard's settings step stores the number of choices and the results
 * visibility, the content step the ordered options.
 *
 * Validation of both steps happens in the save actions with translated
 * messages; rules() only shapes the input. Polls are anonymous
 * (SCOPE-013, SEC-009): they have no submissions overview.
 */
final class PollType implements ActivityType
{
    public function __construct(
        private readonly SavePollSettings $settings,
        private readonly SavePollOptions $options,
    ) {}

    public function kind(): ActivityKind
    {
        return ActivityKind::Poll;
    }

    public function component(WizardStep $step): ?string
    {
        return match ($step) {
            WizardStep::Settings => 'Activities/Poll/Settings',
            WizardStep::Content => 'Activities/Poll/Options',
            default => null,
        };
    }

    public function stepData(Activity $activity, WizardStep $step): ?array
    {
        $poll = Poll::forActivity($activity);

        return match ($step) {
            WizardStep::Settings => SavePollSettings::data($poll),
            WizardStep::Content => SavePollOptions::data($poll),
            default => null,
        };
    }

    public function rules(Activity $activity, WizardStep $step): array
    {
        return match ($step) {
            WizardStep::Settings => array_fill_keys(['max_choices', 'results_visibility'], ['nullable']),
            WizardStep::Content => [
                'options' => ['present', 'array', 'list'],
                'options.*' => ['array'],
            ],
            default => [],
        };
    }

    public function save(Activity $activity, WizardStep $step, array $data): void
    {
        match ($step) {
            WizardStep::Settings => $this->settings->handle($activity, $data),
            WizardStep::Content => $this->options->handle($activity, $data),
            default => null,
        };
    }

    /**
     * Once anyone has voted, the votes count for the options as they were:
     * the number of choices and the set of options and their labels are
     * locked. The order of the options and the results visibility are not.
     */
    public function changesGrading(Activity $activity, WizardStep $step, array $data): bool
    {
        $stored = $this->stepData($activity, $step) ?? [];

        return match ($step) {
            WizardStep::Settings => (int) ($stored['max_choices'] ?? 0) !== (int) (is_numeric($data['max_choices'] ?? null) ? $data['max_choices'] : 0),
            WizardStep::Content => self::optionFingerprint($stored) !== self::optionFingerprint($data),
            default => false,
        };
    }

    public function publishErrors(Activity $activity): array
    {
        $poll = Poll::forActivity($activity);
        $errors = [];

        if ($poll === null) {
            $errors['settings'] = (string) __('activities.poll.errors.settings_missing');

            return $errors;
        }

        $count = $poll->options()->count();

        if ($count < SavePollOptions::MIN_OPTIONS) {
            $errors['options'] = (string) __('activities.poll.errors.min_options', ['min' => SavePollOptions::MIN_OPTIONS]);
        } elseif ($poll->max_choices > $count) {
            $errors['max_choices'] = (string) __('activities.poll.errors.max_choices_above_options', ['count' => $count]);
        }

        return $errors;
    }

    public function hasSubmissions(Activity $activity): bool
    {
        return PollParticipation::query()->where('poll_id', $activity->id)->exists();
    }

    /**
     * A poll is anonymous: nobody sees who took part (SEC-009), so there
     * is no submissions overview; the teacher sees the tally instead.
     */
    public function submissions(Activity $activity, ?SubmissionStatus $status): array
    {
        return [];
    }

    /**
     * The options by id with their labels, regardless of order.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private static function optionFingerprint(array $data): array
    {
        $fingerprint = [];

        foreach (is_array($data['options'] ?? null) ? $data['options'] : [] as $option) {
            if (is_array($option) && is_string($option['id'] ?? null)) {
                $fingerprint[$option['id']] = trim(is_string($option['label'] ?? null) ? $option['label'] : '');
            }
        }

        ksort($fingerprint);

        return $fingerprint;
    }
}

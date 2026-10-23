<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Poll;
use App\Modules\Activities\Models\PollOption;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * UC-022 step 3 for a poll (wizard step "content"): the options of the
 * poll in their order. The client identifies every option by a UUID, which
 * it generates for new ones, so autosaving the same list again changes
 * nothing; an id that belongs to an option outside this poll is refused.
 * Options left out are deleted. Called by the poll's ActivityType inside
 * the wizard's transaction, after the vote lock was checked.
 */
class SavePollOptions
{
    public const int MIN_OPTIONS = 2;

    public const int MAX_OPTIONS = 20;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Activity $activity, array $input): void
    {
        $poll = Poll::forActivity($activity);

        if ($poll === null) {
            throw ValidationException::withMessages(['options' => (string) __('activities.poll.errors.settings_first')]);
        }

        $options = $this->validate($poll, $input);

        /** @var array<string, PollOption> $existing */
        $existing = $poll->options()->get()->keyBy('id')->all();
        $kept = [];

        foreach ($options as $position => $item) {
            $option = $existing[$item['id']] ?? null;

            if ($option === null) {
                $option = new PollOption;
                $option->id = $item['id'];
                $option->organization_id = $poll->organization_id;
                $option->poll_id = $poll->activity_id;
            }

            $option->fill(['label' => $item['label'], 'position' => $position])->save();
            $kept[$item['id']] = true;
        }

        $removed = array_values(array_diff(array_keys($existing), array_keys($kept)));

        if ($removed !== []) {
            PollOption::query()->where('poll_id', $poll->activity_id)->whereKey($removed)->delete();
        }
    }

    /**
     * The saved options in the shape of the wizard step, or null when the
     * poll has no options yet.
     *
     * @return array{options: list<array{id: string, label: string}>}|null
     */
    public static function data(?Poll $poll): ?array
    {
        if ($poll === null) {
            return null;
        }

        $options = $poll->options()->get()
            ->map(fn (PollOption $option): array => ['id' => $option->id, 'label' => $option->label])
            ->values()
            ->all();

        return $options === [] ? null : ['options' => $options];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{id: string, label: string}>
     *
     * @throws ValidationException
     */
    private function validate(Poll $poll, array $input): array
    {
        $validator = Validator::make($input, [
            'options' => ['present', 'array', 'list', 'min:'.self::MIN_OPTIONS, 'max:'.self::MAX_OPTIONS],
            'options.*' => ['array'],
            'options.*.id' => ['required', 'uuid', 'distinct'],
            'options.*.label' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
        ], [
            'options.min' => (string) __('activities.poll.errors.min_options', ['min' => self::MIN_OPTIONS]),
            'options.max' => (string) __('activities.poll.errors.max_options', ['max' => self::MAX_OPTIONS]),
            'options.*.label.distinct' => (string) __('activities.poll.errors.duplicate_label'),
        ], [
            'options' => (string) __('activities.poll.attributes.options'),
            'options.*.id' => (string) __('activities.poll.attributes.option'),
            'options.*.label' => (string) __('activities.poll.attributes.label'),
        ]);

        /** @var array{options: list<array{id: string, label: string}>} $data */
        $data = $validator->validate();
        $options = array_map(static fn (array $option): array => [
            'id' => (string) $option['id'],
            'label' => trim((string) $option['label']),
        ], $data['options']);

        $ownIds = $poll->options()->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();
        $newIds = array_values(array_diff(array_column($options, 'id'), $ownIds));
        $taken = $newIds === [] ? [] : PollOption::query()->withoutGlobalScopes()->whereKey($newIds)
            ->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();
        $errors = [];

        foreach ($options as $index => $option) {
            // A primary key is global: only the poll's own options may be addressed.
            if (in_array($option['id'], $taken, true)) {
                $errors["options.{$index}.id"] = [(string) __('activities.poll.errors.foreign_id')];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $options;
    }
}

<?php

namespace App\Modules\Activities\Support;

use App\Modules\Activities\Models\Activity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Wizard step "basics" of every activity (UC-022 step 1): title,
 * description, deadline, and whether it is individual or a group activity
 * with its maximum group size (SCOPE-012, SCOPE-017). The course space and
 * the type are chosen when the activity is created and do not change
 * afterwards. Basics saved without is_group leave the group settings as
 * they are.
 */
final class Basics
{
    public const int MIN_GROUP_SIZE = 2;

    public const int MAX_GROUP_SIZE = 100;

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'deadline_at' => ['nullable', 'date'],
            'is_group' => ['sometimes', 'boolean'],
            'group_size' => ['nullable', 'required_if_accepted:is_group', 'integer', 'min:'.self::MIN_GROUP_SIZE, 'max:'.self::MAX_GROUP_SIZE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'title' => (string) __('activities.attributes.title'),
            'description' => (string) __('activities.attributes.description'),
            'deadline_at' => (string) __('activities.attributes.deadline_at'),
            'course_space_id' => (string) __('activities.attributes.course_space_id'),
            'type' => (string) __('activities.attributes.type'),
            'is_group' => (string) __('activities.attributes.is_group'),
            'group_size' => (string) __('activities.attributes.group_size'),
        ];
    }

    /**
     * Validates the basics; the deadline is stored in UTC (DATA-007).
     *
     * @param  array<string, mixed>  $input
     * @return array{title: string, description: string|null, deadline_at: Carbon|null, is_group?: bool, group_size?: int|null}
     */
    public static function validate(array $input): array
    {
        return self::normalize(Validator::make($input, self::rules(), [], self::attributes())->validate());
    }

    /**
     * @param  array<string, mixed>  $data  validated basics
     * @return array{title: string, description: string|null, deadline_at: Carbon|null, is_group?: bool, group_size?: int|null}
     */
    public static function normalize(array $data): array
    {
        $deadline = $data['deadline_at'] ?? null;
        $basics = [
            'title' => (string) $data['title'],
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'deadline_at' => is_string($deadline) && $deadline !== '' ? Carbon::parse($deadline)->utc() : null,
        ];

        if (array_key_exists('is_group', $data)) {
            $isGroup = filter_var($data['is_group'], FILTER_VALIDATE_BOOLEAN);
            $size = $data['group_size'] ?? null;
            $basics['is_group'] = $isGroup;
            $basics['group_size'] = $isGroup && is_numeric($size) ? (int) $size : null;
        }

        return $basics;
    }

    /**
     * @return array{title: string, description: string|null, deadline_at: string|null, is_group: bool, group_size: int|null}
     */
    public static function of(Activity $activity): array
    {
        return [
            'title' => $activity->title,
            'description' => $activity->description,
            'deadline_at' => $activity->deadline_at?->utc()->toIso8601String(),
            'is_group' => $activity->is_group,
            'group_size' => $activity->group_size,
        ];
    }

    /**
     * What keeps the basics from being published, as field => message: a
     * deadline that has already passed (server time, DATA-004).
     *
     * @return array<string, string>
     */
    public static function publishErrors(Activity $activity): array
    {
        $errors = [];

        if (blank($activity->title)) {
            $errors['title'] = (string) __('validation.required', ['attribute' => __('activities.attributes.title')]);
        }

        if ($activity->deadline_at !== null && $activity->deadline_at->isPast()) {
            $errors['deadline_at'] = (string) __('activities.errors.deadline_passed');
        }

        return $errors;
    }
}

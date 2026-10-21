<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\QuizMode;
use App\Modules\Assessment\Enums\ResultVisibility;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Support\QuestionRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * UC-022 step 3: the settings of a quiz (wizard step "settings"): mode
 * (only self-paced in this phase), time limit in minutes, attempts,
 * negative marking with its percentage of the question's points, pass mark
 * and result visibility. Creates the quiz subtype row on the first save.
 * Called by the quiz's ActivityType inside the wizard's transaction.
 */
class SaveQuizSettings
{
    public const int MAX_TIME_LIMIT_MINUTES = 1440;

    public const int MAX_ATTEMPTS = 100;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Activity $activity, array $input): Quiz
    {
        $data = Validator::make($input, self::rules(), [], self::attributes())->validate();

        $quiz = Quiz::forActivity($activity) ?? new Quiz;

        if (! $quiz->exists) {
            $quiz->activity_id = $activity->id;
            $quiz->organization_id = $activity->organization_id;
        }

        $negative = filter_var($data['negative_marking'], FILTER_VALIDATE_BOOLEAN);
        $minutes = $data['time_limit_minutes'] ?? null;

        $quiz->fill([
            'mode' => QuizMode::from((string) $data['mode']),
            'time_limit' => $minutes === null ? null : (int) $minutes * 60,
            'max_attempts' => (int) $data['max_attempts'],
            'negative_marking' => $negative,
            'negative_mark_fraction' => $negative ? QuestionRules::decimal($data['negative_mark_fraction']) : null,
            'pass_mark_percentage' => ($data['pass_mark_percentage'] ?? null) === null ? null : QuestionRules::decimal($data['pass_mark_percentage']),
            'score_visibility' => ResultVisibility::from((string) $data['score_visibility']),
            'answers_visibility' => ResultVisibility::from((string) $data['answers_visibility']),
        ])->save();

        return $quiz;
    }

    /**
     * The saved settings in the shape of the wizard step, or null when the
     * quiz has no settings yet.
     *
     * @return array{mode: string, time_limit_minutes: int|null, max_attempts: int, negative_marking: bool, negative_mark_fraction: string|null, pass_mark_percentage: string|null, score_visibility: string, answers_visibility: string}|null
     */
    public static function data(?Quiz $quiz): ?array
    {
        if ($quiz === null) {
            return null;
        }

        return [
            'mode' => $quiz->mode->value,
            'time_limit_minutes' => $quiz->time_limit === null ? null : intdiv($quiz->time_limit, 60),
            'max_attempts' => $quiz->max_attempts,
            'negative_marking' => $quiz->negative_marking,
            'negative_mark_fraction' => $quiz->negative_mark_fraction,
            'pass_mark_percentage' => $quiz->pass_mark_percentage,
            'score_visibility' => $quiz->score_visibility->value,
            'answers_visibility' => $quiz->answers_visibility->value,
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private static function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(QuizMode::offered())],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_TIME_LIMIT_MINUTES],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:'.self::MAX_ATTEMPTS],
            'negative_marking' => ['required', 'boolean'],
            'negative_mark_fraction' => ['exclude_unless:negative_marking,true,1', 'required', 'numeric', 'decimal:0,2', 'gt:0', 'lte:100'],
            'pass_mark_percentage' => ['nullable', 'numeric', 'decimal:0,2', 'gte:0', 'lte:100'],
            'score_visibility' => ['required', Rule::enum(ResultVisibility::class)],
            'answers_visibility' => ['required', Rule::enum(ResultVisibility::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function attributes(): array
    {
        return [
            'mode' => (string) __('assessment.quiz.settings.mode'),
            'time_limit_minutes' => (string) __('assessment.quiz.settings.time_limit'),
            'max_attempts' => (string) __('assessment.quiz.settings.max_attempts'),
            'negative_marking' => (string) __('assessment.quiz.settings.negative_marking'),
            'negative_mark_fraction' => (string) __('assessment.quiz.settings.negative_mark_fraction'),
            'pass_mark_percentage' => (string) __('assessment.quiz.settings.pass_mark'),
            'score_visibility' => (string) __('assessment.quiz.settings.score_visibility'),
            'answers_visibility' => (string) __('assessment.quiz.settings.answers_visibility'),
        ];
    }
}

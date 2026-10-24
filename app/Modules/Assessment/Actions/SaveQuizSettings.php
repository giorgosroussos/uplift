<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\LiveStartMode;
use App\Modules\Assessment\Enums\QuestionType;
use App\Modules\Assessment\Enums\QuizMode;
use App\Modules\Assessment\Enums\ResultVisibility;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Assessment\Support\QuestionRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * UC-022 step 3: the settings of a quiz (wizard step "settings"). The mode
 * is self-paced or live (SCOPE-016).
 *
 * Self-paced: time limit in minutes, attempts, negative marking with its
 * percentage of the question's points, pass mark and result visibility.
 *
 * Live (SCOPE-021): the duration (time limit, required) and the start mode,
 * manual from a lobby or scheduled at a future time (stored in UTC). A live
 * quiz runs once and is taken in a live session, not in attempts: it has no
 * negative marking (SCOPE-022, refused), one run, no pass mark, and shows
 * score and correct answers when the session finishes; it takes no
 * free-text question (SCOPE-019), so switching a quiz that has one to live
 * is refused.
 *
 * Creates the quiz subtype row on the first save. Called by the quiz's
 * ActivityType inside the wizard's transaction.
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
        $data = Validator::make($input, self::rules($input), self::messages(), self::attributes())->validate();

        $quiz = Quiz::forActivity($activity) ?? new Quiz;
        $mode = QuizMode::from((string) $data['mode']);

        if ($mode === QuizMode::Live) {
            $this->checkLive($quiz, $data);
        }

        if (! $quiz->exists) {
            $quiz->activity_id = $activity->id;
            $quiz->organization_id = $activity->organization_id;
        }

        $minutes = $data['time_limit_minutes'] ?? null;
        $timeLimit = $minutes === null ? null : (int) $minutes * 60;

        $quiz->fill($mode === QuizMode::Live ? self::live($data, $timeLimit) : self::selfPaced($data, $timeLimit))->save();

        return $quiz;
    }

    /**
     * The saved settings in the shape of the wizard step, or null when the
     * quiz has no settings yet.
     *
     * @return array{mode: string, time_limit_minutes: int|null, live_start_mode: string|null, live_scheduled_start_at: string|null, max_attempts: int, negative_marking: bool, negative_mark_fraction: string|null, pass_mark_percentage: string|null, score_visibility: string, answers_visibility: string}|null
     */
    public static function data(?Quiz $quiz): ?array
    {
        if ($quiz === null) {
            return null;
        }

        return [
            'mode' => $quiz->mode->value,
            'time_limit_minutes' => $quiz->time_limit === null ? null : intdiv($quiz->time_limit, 60),
            'live_start_mode' => $quiz->live_start_mode?->value,
            'live_scheduled_start_at' => $quiz->live_scheduled_start_at?->utc()->toIso8601String(),
            'max_attempts' => $quiz->max_attempts,
            'negative_marking' => $quiz->negative_marking,
            'negative_mark_fraction' => $quiz->negative_mark_fraction,
            'pass_mark_percentage' => $quiz->pass_mark_percentage,
            'score_visibility' => $quiz->score_visibility->value,
            'answers_visibility' => $quiz->answers_visibility->value,
        ];
    }

    /**
     * A free-text question cannot be in a live quiz (SCOPE-019), and a
     * scheduled start that is set or moved must lie in the future.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function checkLive(Quiz $quiz, array $data): void
    {
        $errors = [];

        if ($quiz->exists && QuizQuestion::query()
            ->where('quiz_id', $quiz->activity_id)
            ->whereHas('question', fn ($query) => $query->where('type', QuestionType::FreeText->value))
            ->exists()) {
            $errors['mode'] = [(string) __('assessment.quiz.errors.live_has_free_text')];
        }

        if (isset($data['live_scheduled_start_at'])) {
            $startsAt = CarbonImmutable::parse((string) $data['live_scheduled_start_at'])->utc();
            $unchanged = $quiz->live_scheduled_start_at !== null && $quiz->live_scheduled_start_at->equalTo($startsAt);

            if (! $unchanged && ! $startsAt->isFuture()) {
                $errors['live_scheduled_start_at'] = [(string) __('assessment.quiz.errors.live_start_in_past')];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function selfPaced(array $data, ?int $timeLimit): array
    {
        $negative = filter_var($data['negative_marking'], FILTER_VALIDATE_BOOLEAN);

        return [
            'mode' => QuizMode::SelfPaced,
            'time_limit' => $timeLimit,
            'live_start_mode' => null,
            'live_scheduled_start_at' => null,
            'max_attempts' => (int) $data['max_attempts'],
            'negative_marking' => $negative,
            'negative_mark_fraction' => $negative ? QuestionRules::decimal($data['negative_mark_fraction']) : null,
            'pass_mark_percentage' => ($data['pass_mark_percentage'] ?? null) === null ? null : QuestionRules::decimal($data['pass_mark_percentage']),
            'score_visibility' => ResultVisibility::from((string) $data['score_visibility']),
            'answers_visibility' => ResultVisibility::from((string) $data['answers_visibility']),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function live(array $data, ?int $timeLimit): array
    {
        $startMode = LiveStartMode::from((string) $data['live_start_mode']);

        return [
            'mode' => QuizMode::Live,
            'time_limit' => $timeLimit,
            'live_start_mode' => $startMode,
            'live_scheduled_start_at' => $startMode === LiveStartMode::Scheduled
                ? CarbonImmutable::parse((string) $data['live_scheduled_start_at'])->utc()
                : null,
            'max_attempts' => 1,
            'negative_marking' => false,
            'negative_mark_fraction' => null,
            'pass_mark_percentage' => null,
            'score_visibility' => ResultVisibility::Immediate,
            'answers_visibility' => ResultVisibility::Immediate,
        ];
    }

    /**
     * Live-only fields are left out of a self-paced quiz and self-paced-only
     * fields out of a live one.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, mixed>>
     */
    private static function rules(array $input): array
    {
        $live = ($input['mode'] ?? null) === QuizMode::Live->value;

        if ($live) {
            return [
                'mode' => ['required', Rule::in(QuizMode::offered())],
                'time_limit_minutes' => ['required', 'integer', 'min:1', 'max:'.self::MAX_TIME_LIMIT_MINUTES],
                'live_start_mode' => ['required', Rule::enum(LiveStartMode::class)],
                'live_scheduled_start_at' => ['exclude_unless:live_start_mode,'.LiveStartMode::Scheduled->value, 'required', 'date'],
                'negative_marking' => ['nullable', 'boolean', 'declined'],
            ];
        }

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
    private static function messages(): array
    {
        return [
            'negative_marking.declined' => (string) __('assessment.quiz.errors.live_negative_marking'),
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
            'live_start_mode' => (string) __('assessment.quiz.settings.live_start_mode'),
            'live_scheduled_start_at' => (string) __('assessment.quiz.settings.live_scheduled_start_at'),
            'max_attempts' => (string) __('assessment.quiz.settings.max_attempts'),
            'negative_marking' => (string) __('assessment.quiz.settings.negative_marking'),
            'negative_mark_fraction' => (string) __('assessment.quiz.settings.negative_mark_fraction'),
            'pass_mark_percentage' => (string) __('assessment.quiz.settings.pass_mark'),
            'score_visibility' => (string) __('assessment.quiz.settings.score_visibility'),
            'answers_visibility' => (string) __('assessment.quiz.settings.answers_visibility'),
        ];
    }
}

<?php

namespace Tests\Support\Activities;

use App\Modules\Activities\Contracts\ActivityType;
use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\SubmissionStatus;
use App\Modules\Activities\Enums\WizardStep;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\Submission;
use Illuminate\Validation\ValidationException;

/**
 * A quiz-like ActivityType for tests of the Activities framework, standing
 * in for the real quiz type (P3-T3). Keeps its data in memory:
 * settings {mode, max_attempts, negative_marking}, content {questions:
 * [{type, prompt, correct}]} and submissions per activity.
 *
 * Mirrors the quiz rules the framework must enforce through the contract:
 * a quiz needs at least one question to be published; a live quiz takes no
 * free-text question; correct answers and negative marking change grading.
 */
final class FakeQuizType implements ActivityType
{
    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    public static array $data = [];

    /**
     * @var array<string, list<Submission>>
     */
    public static array $submissions = [];

    public static function reset(): void
    {
        self::$data = [];
        self::$submissions = [];
    }

    public function kind(): ActivityKind
    {
        return ActivityKind::Quiz;
    }

    public function component(WizardStep $step): ?string
    {
        return match ($step) {
            WizardStep::Settings => 'Testing/QuizSettings',
            WizardStep::Content => 'Testing/QuizQuestions',
            default => null,
        };
    }

    public function stepData(Activity $activity, WizardStep $step): ?array
    {
        return self::$data[$activity->id][$step->value] ?? null;
    }

    public function rules(Activity $activity, WizardStep $step): array
    {
        return match ($step) {
            WizardStep::Settings => [
                'mode' => ['required', 'in:self_paced,live'],
                'max_attempts' => ['required', 'integer', 'min:1'],
                'negative_marking' => ['required', 'boolean'],
            ],
            WizardStep::Content => [
                'questions' => ['present', 'array'],
                'questions.*.type' => ['required', 'in:single_choice,multiple_choice,true_false,matching,free_text'],
                'questions.*.prompt' => ['required', 'string'],
                'questions.*.correct' => ['nullable', 'string'],
            ],
            default => [],
        };
    }

    public function save(Activity $activity, WizardStep $step, array $data): void
    {
        if ($step === WizardStep::Content && ($this->stepData($activity, WizardStep::Settings)['mode'] ?? null) === 'live') {
            /** @var list<array{type: string}> $questions */
            $questions = $data['questions'];

            foreach ($questions as $index => $question) {
                if ($question['type'] === 'free_text') {
                    throw ValidationException::withMessages([
                        "questions.{$index}.type" => 'A live quiz cannot have free-text questions.',
                    ]);
                }
            }
        }

        self::$data[$activity->id][$step->value] = $data;
    }

    public function changesGrading(Activity $activity, WizardStep $step, array $data): bool
    {
        $stored = $this->stepData($activity, $step) ?? [];

        return match ($step) {
            WizardStep::Settings => ($stored['negative_marking'] ?? null) !== ($data['negative_marking'] ?? null),
            WizardStep::Content => array_map(fn (array $q) => [$q['type'], $q['correct'] ?? null], $stored['questions'] ?? [])
                !== array_map(fn (array $q) => [$q['type'], $q['correct'] ?? null], $data['questions'] ?? []),
            default => false,
        };
    }

    public function publishErrors(Activity $activity): array
    {
        $errors = [];

        if ($this->stepData($activity, WizardStep::Settings) === null) {
            $errors['settings'] = 'The quiz settings are missing.';
        }

        if (($this->stepData($activity, WizardStep::Content)['questions'] ?? []) === []) {
            $errors['questions'] = 'A quiz needs at least one question.';
        }

        return $errors;
    }

    public function hasSubmissions(Activity $activity): bool
    {
        return (self::$submissions[$activity->id] ?? []) !== [];
    }

    public function submissions(Activity $activity, ?SubmissionStatus $status): array
    {
        return array_values(array_filter(
            self::$submissions[$activity->id] ?? [],
            fn (Submission $submission): bool => $status === null || $submission->status === $status,
        ));
    }
}

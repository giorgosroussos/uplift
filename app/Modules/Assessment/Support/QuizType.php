<?php

namespace App\Modules\Assessment\Support;

use App\Modules\Activities\Contracts\ActivityType;
use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\SubmissionStatus;
use App\Modules\Activities\Enums\WizardStep;
use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Actions\ListQuizSubmissions;
use App\Modules\Assessment\Actions\SaveQuizQuestions;
use App\Modules\Assessment\Actions\SaveQuizSettings;
use App\Modules\Assessment\Actions\ShowQuizQuestions;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;

/**
 * The quiz activity type (ARCH-004), registered with the Activities
 * registry by AssessmentServiceProvider. Its subtype row is ENT-Quiz; the
 * wizard's settings step stores the quiz settings, the content step the
 * ordered quiz questions (copies, DATA-008).
 *
 * Validation of both steps happens in the save actions with translated
 * messages; rules() only shapes the input.
 */
final class QuizType implements ActivityType
{
    public function __construct(
        private readonly SaveQuizSettings $settings,
        private readonly SaveQuizQuestions $questions,
        private readonly ShowQuizQuestions $show,
        private readonly ListQuizSubmissions $submissions,
    ) {}

    public function kind(): ActivityKind
    {
        return ActivityKind::Quiz;
    }

    public function component(WizardStep $step): ?string
    {
        return match ($step) {
            WizardStep::Settings => 'Assessment/Quiz/Settings',
            WizardStep::Content => 'Assessment/Quiz/Questions',
            default => null,
        };
    }

    public function stepData(Activity $activity, WizardStep $step): ?array
    {
        $quiz = Quiz::forActivity($activity);

        return match ($step) {
            WizardStep::Settings => SaveQuizSettings::data($quiz),
            WizardStep::Content => $this->content($quiz),
            default => null,
        };
    }

    public function rules(Activity $activity, WizardStep $step): array
    {
        return match ($step) {
            WizardStep::Settings => array_fill_keys([
                'mode', 'time_limit_minutes', 'live_start_mode', 'live_scheduled_start_at', 'max_attempts', 'negative_marking',
                'negative_mark_fraction', 'pass_mark_percentage', 'score_visibility', 'answers_visibility',
            ], ['nullable']),
            WizardStep::Content => [
                'questions' => ['present', 'array', 'list'],
                'questions.*' => ['array'],
            ],
            default => [],
        };
    }

    public function save(Activity $activity, WizardStep $step, array $data): void
    {
        match ($step) {
            WizardStep::Settings => $this->settings->handle($activity, $data),
            WizardStep::Content => $this->questions->handle($activity, $data),
            default => null,
        };
    }

    /**
     * Grading changes with the mode (self-paced attempts or a live run,
     * SCOPE-016), negative marking or the pass mark (settings) and
     * with the set of questions, their type and points, and which options
     * and pairs they have and which options are correct (content). Order,
     * prompts and item texts do not change grading.
     */
    public function changesGrading(Activity $activity, WizardStep $step, array $data): bool
    {
        $stored = $this->stepData($activity, $step);

        return match ($step) {
            WizardStep::Settings => GradingFingerprint::settings($stored ?? []) !== GradingFingerprint::settings($data),
            WizardStep::Content => GradingFingerprint::questions($stored ?? []) !== GradingFingerprint::questions($data),
            default => false,
        };
    }

    public function publishErrors(Activity $activity): array
    {
        $quiz = Quiz::forActivity($activity);
        $errors = [];

        if ($quiz === null) {
            $errors['settings'] = (string) __('assessment.quiz.errors.settings_missing');
        }

        if ($quiz === null || ! $quiz->quizQuestions()->exists()) {
            $errors['questions'] = (string) __('assessment.quiz.errors.no_questions');
        }

        return $errors;
    }

    /**
     * Any attempt, even one in progress, locks grading: its answers refer
     * to the quiz's questions and options.
     */
    public function hasSubmissions(Activity $activity): bool
    {
        return QuizAttempt::query()->where('quiz_id', $activity->id)->exists();
    }

    /**
     * UC-028: the submitted attempts, needing grading or graded.
     */
    public function submissions(Activity $activity, ?SubmissionStatus $status): array
    {
        return $this->submissions->handle($activity, $status);
    }

    /**
     * @return array{questions: list<array<string, mixed>>}|null
     */
    private function content(?Quiz $quiz): ?array
    {
        if ($quiz === null) {
            return null;
        }

        $questions = $this->show->handle($quiz);

        return $questions === [] ? null : ['questions' => $questions];
    }
}

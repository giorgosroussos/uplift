<?php

namespace App\Modules\Live\Support;

use App\Modules\Activities\Contracts\ActivityType;
use App\Modules\Activities\Contracts\ProvidesActivityActions;
use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\SubmissionStatus;
use App\Modules\Activities\Enums\WizardStep;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\ActivityAction;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Support\QuizType;
use App\Modules\Identity\Models\User;
use App\Modules\Live\Enums\LiveSessionStatus;
use App\Modules\Live\Models\LiveSession;
use Illuminate\Support\Facades\Gate;

/**
 * The quiz activity type as Live extends it (ARCH-004): everything is the
 * quiz's own behaviour (Assessment's QuizType), except that a quiz also
 * has submissions once a live session of it has started (SCOPE-029). So a
 * live quiz that ran can no longer be deleted, nor its grading (mode,
 * questions, points) changed; a lobby that never started, or was closed,
 * does not count. LiveServiceProvider registers it in place of QuizType,
 * without editing Activities or Assessment.
 *
 * It also contributes the quiz's activity page actions (ARCH-009), so the
 * activity page holds no quiz route: 'Open quiz' (open_quiz) to the
 * self-paced quiz page for a student who may take it, never on a live
 * quiz; 'Open live quiz' (open_live_quiz) to the monitor for the owning
 * teacher of a published live quiz.
 */
final class LiveQuizType implements ActivityType, ProvidesActivityActions
{
    public function __construct(private readonly QuizType $quiz) {}

    public function kind(): ActivityKind
    {
        return $this->quiz->kind();
    }

    public function component(WizardStep $step): ?string
    {
        return $this->quiz->component($step);
    }

    public function stepData(Activity $activity, WizardStep $step): ?array
    {
        return $this->quiz->stepData($activity, $step);
    }

    public function rules(Activity $activity, WizardStep $step): array
    {
        return $this->quiz->rules($activity, $step);
    }

    public function save(Activity $activity, WizardStep $step, array $data): void
    {
        $this->quiz->save($activity, $step, $data);
    }

    public function changesGrading(Activity $activity, WizardStep $step, array $data): bool
    {
        return $this->quiz->changesGrading($activity, $step, $data);
    }

    public function publishErrors(Activity $activity): array
    {
        return $this->quiz->publishErrors($activity);
    }

    public function hasSubmissions(Activity $activity): bool
    {
        return $this->quiz->hasSubmissions($activity) || self::hasStartedSession($activity);
    }

    public function submissions(Activity $activity, ?SubmissionStatus $status): array
    {
        return $this->quiz->submissions($activity, $status);
    }

    public function actions(Activity $activity, User $user): array
    {
        $quiz = Quiz::forActivity($activity);

        if ($quiz === null) {
            return [];
        }

        $gate = Gate::forUser($user);

        if ($quiz->isLive()) {
            return $activity->isPublished() && $gate->allows('open', [LiveSession::class, $activity])
                ? [new ActivityAction('open_live_quiz', route('live.monitor', $activity, false), 'live.actions.open_live_quiz')]
                : [];
        }

        // The self-paced quiz page: its rules, the student's attempts and
        // results (QuizAttemptPolicy::take: an enrolled student, once published).
        return $gate->allows('take', [QuizAttempt::class, $activity])
            ? [new ActivityAction('open_quiz', route('assessment.quizzes.show', $activity, false), 'live.actions.open_quiz')]
            : [];
    }

    public static function hasStartedSession(Activity $activity): bool
    {
        return LiveSession::query()
            ->where('quiz_id', $activity->id)
            ->whereIn('status', LiveSessionStatus::started())
            ->exists();
    }
}

<?php

namespace App\Modules\Live\Support;

use App\Modules\Activities\Contracts\ActivityType;
use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\SubmissionStatus;
use App\Modules\Activities\Enums\WizardStep;
use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Support\QuizType;
use App\Modules\Live\Enums\LiveSessionStatus;
use App\Modules\Live\Models\LiveSession;

/**
 * The quiz activity type as Live extends it (ARCH-004): everything is the
 * quiz's own behaviour (Assessment's QuizType), except that a quiz also
 * has submissions once a live session of it has started (SCOPE-029). So a
 * live quiz that ran can no longer be deleted, nor its grading (mode,
 * questions, points) changed; a lobby that never started, or was closed,
 * does not count. LiveServiceProvider registers it in place of QuizType,
 * without editing Activities or Assessment.
 */
final class LiveQuizType implements ActivityType
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

    public static function hasStartedSession(Activity $activity): bool
    {
        return LiveSession::query()
            ->where('quiz_id', $activity->id)
            ->whereIn('status', LiveSessionStatus::started())
            ->exists();
    }
}

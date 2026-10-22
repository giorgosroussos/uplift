<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\GradingStatus;
use App\Modules\Assessment\Events\GradeFinalised;
use App\Modules\Assessment\Events\ResultChanged;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Support\Points;
use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-026 step 2-3 (SCOPE-023, SCOPE-024): the owning teacher grades an
 * answer of a submitted attempt with points from 0 to the question's
 * maximum (at most 2 decimals) and optional feedback. A pending free-text
 * answer or an auto-graded one (regrade) becomes graded; a graded answer
 * may be corrected. The attempt's earned_points is recalculated as the sum
 * of its answers' points, never below 0. Once no answer waits for grading,
 * a submitted attempt becomes graded (final score) and GradeFinalised is
 * dispatched after commit; its result is released at once when the student
 * may see it now (DEC-003, ReleaseResult). A regrade that changes
 * earned_points of a released attempt dispatches ResultChanged; regrades
 * before the release dispatch nothing (DEC-003).
 *
 * Validation messages never repeat the answer, the feedback or the points
 * (SEC-003).
 */
class GradeAnswer
{
    public const int FEEDBACK_MAX = 5000;

    public function __construct(private readonly ReleaseResult $release) {}

    /**
     * @param  array<string, mixed>  $data  points (decimal string) and feedback (optional)
     *
     * @throws ValidationException
     */
    public function handle(Answer $answer, User $teacher, array $data): Answer
    {
        $question = $answer->quizQuestion->question;
        $points = $this->points($data['points'] ?? null, (string) $question->points);
        $feedback = $this->feedback($data['feedback'] ?? null);

        return DB::transaction(function () use ($answer, $teacher, $points, $feedback): Answer {
            $attempt = QuizAttempt::query()->lockForUpdate()->findOrFail($answer->attempt_id);

            if ($attempt->status === AttemptStatus::InProgress) {
                throw ValidationException::withMessages(['points' => (string) __('assessment.grading.errors.not_submitted')]);
            }

            $locked = Answer::query()->lockForUpdate()->findOrFail($answer->id);
            $locked->points_awarded = Points::format($points);
            $locked->feedback = $feedback;
            $locked->graded_by = $teacher->getKey();
            $locked->graded_at = CarbonImmutable::now();

            if ($locked->grading_status === GradingStatus::Graded) {
                $locked->save();
            } else {
                $locked->transitionTo(GradingStatus::Graded);
            }

            $total = 0;

            foreach ($attempt->answers()->whereNotNull('points_awarded')->pluck('points_awarded') as $awarded) {
                $total += Points::toHundredths((string) $awarded);
            }

            $before = Points::toHundredths((string) ($attempt->earned_points ?? '0'));
            $after = max(0, $total);
            $attempt->earned_points = Points::format($after);
            $attempt->save();

            if ($attempt->result_released_at !== null && $before !== $after) {
                ResultChanged::dispatch($attempt->organization_id, $attempt->id);
            }

            if ($attempt->status === AttemptStatus::Submitted && ! $attempt->hasPendingAnswers()) {
                $attempt->transitionTo(AttemptStatus::Graded);
                GradeFinalised::dispatch($attempt->organization_id, $attempt->id);
                $this->release->handle($attempt);
            }

            $answer->setRawAttributes($locked->getAttributes(), true);

            return $answer;
        });
    }

    /**
     * UC-026: from 0 to the question's points, with at most 2 decimals,
     * compared exactly in hundredths (DATA-003).
     *
     * @return int hundredths
     */
    private function points(mixed $value, string $max): int
    {
        $text = is_string($value) || is_int($value) || is_float($value) ? trim((string) $value) : '';

        if ($text === '' || preg_match('/^\d{1,3}(\.\d{1,2})?$/', $text) !== 1) {
            throw ValidationException::withMessages(['points' => (string) __('assessment.grading.errors.points_format')]);
        }

        $points = Points::toHundredths($text);

        if ($points > Points::toHundredths($max)) {
            throw ValidationException::withMessages(['points' => (string) __('assessment.grading.errors.points_max', ['max' => $max])]);
        }

        return $points;
    }

    private function feedback(mixed $value): ?string
    {
        if ($value !== null && ! is_string($value)) {
            throw ValidationException::withMessages(['feedback' => (string) __('assessment.grading.errors.feedback_invalid')]);
        }

        $feedback = trim((string) $value);

        if (mb_strlen($feedback) > self::FEEDBACK_MAX) {
            throw ValidationException::withMessages(['feedback' => (string) __('assessment.grading.errors.feedback_length', ['max' => self::FEEDBACK_MAX])]);
        }

        return $feedback === '' ? null : $feedback;
    }
}

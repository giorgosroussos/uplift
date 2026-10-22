<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\GradingStatus;
use App\Modules\Assessment\Events\GradeFinalised;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Support\AnswerScorer;
use App\Modules\Assessment\Support\Points;
use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * UC-012 step 3-4 (SCOPE-023, SCOPE-024): submits an in-progress attempt
 * with the answers saved so far, by the student or automatically (time
 * up, deadline, activity closed). Choice, true/false and matching answers
 * are scored at once and become auto_graded; free-text answers stay
 * pending. earned_points is the sum of the stored points, never below 0.
 * Without pending answers the attempt is graded (final score); otherwise
 * it stays submitted (provisional score). A final score dispatches
 * GradeFinalised after commit and is released at once when the student
 * may see it now (DEC-003, ReleaseResult).
 *
 * Submitting an attempt that is no longer in progress changes nothing.
 */
class SubmitQuizAttempt
{
    public function __construct(
        private readonly AnswerScorer $scorer,
        private readonly ReleaseResult $release,
    ) {}

    /**
     * @param  User|null  $by  the student who submits, or null for an automatic submission
     */
    public function handle(QuizAttempt $attempt, ?User $by = null): QuizAttempt
    {
        return DB::transaction(function () use ($attempt, $by): QuizAttempt {
            $locked = QuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($locked->status !== AttemptStatus::InProgress) {
                return $locked;
            }

            $answers = $locked->answers()
                ->with(['quizQuestion.question.options', 'quizQuestion.question.pairs', 'selections', 'matches'])
                ->get();

            $total = 0;
            $pending = false;

            foreach ($answers as $answer) {
                $question = $answer->quizQuestion->question;

                if ($question->type->isAutoGraded()) {
                    $points = $this->scorer->score($answer, $question, $locked->quiz);
                    $answer->points_awarded = Points::format($points);
                    $total += $points;
                }

                $answer->applySubmission();
                $pending = $pending || $answer->grading_status === GradingStatus::Pending;
            }

            $now = CarbonImmutable::now();

            $locked->earned_points = Points::format(max(0, $total));
            // An attempt submitted late by the scheduler ended at ends_at.
            $locked->submitted_at = $locked->ends_at !== null && $locked->ends_at->lessThan($now) ? $locked->ends_at : $now;
            $locked->submitted_by = $by?->getKey();
            $locked->transitionTo(AttemptStatus::Submitted);

            if (! $pending) {
                $locked->transitionTo(AttemptStatus::Graded);
                GradeFinalised::dispatch($locked->organization_id, $locked->id);
                $this->release->handle($locked);
            }

            return $locked;
        });
    }

    /**
     * Submits the attempt when its time is up (server time, DATA-004);
     * any request on such an attempt does this first (SCOPE-024).
     *
     * @return bool whether the attempt was due and has been submitted now
     */
    public function submitIfDue(QuizAttempt $attempt): bool
    {
        if (! $attempt->isDue()) {
            return false;
        }

        $submitted = $this->handle($attempt);
        $attempt->setRawAttributes($submitted->getAttributes(), true);

        return true;
    }
}

<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Support\AttemptReview;
use App\Modules\Assessment\Support\AttemptScore;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * UC-017 step 3: the result of one of the student's own attempts: the
 * score on the organisation's grading scale and the points per question
 * if the quiz shows the score, provisional or final, the answers given,
 * the teacher's feedback, and the correct answers only if the quiz's
 * answer visibility allows it now (SCOPE-023). An attempt whose time is
 * up is submitted first (SCOPE-024).
 *
 * Returns null while the attempt is still in progress: it has no result
 * yet. The result of a deleted activity is not found.
 */
class ShowResult
{
    public function __construct(private readonly SubmitQuizAttempt $submit) {}

    /**
     * @return array<string, mixed>|null
     */
    public function handle(QuizAttempt $attempt): ?array
    {
        $this->submit->submitIfDue($attempt);

        if ($attempt->status === AttemptStatus::InProgress) {
            return null;
        }

        $quiz = $attempt->quiz;
        // A deleted activity has no results to show (as in My results).
        $activity = $quiz->activity()->with('courseSpace')->first();

        if ($activity === null) {
            throw new NotFoundHttpException;
        }

        $score = AttemptScore::visibleTo($quiz, $attempt);
        $answersVisible = $quiz->areAnswersVisible($attempt);

        return [
            'quiz' => [
                'activity_id' => $activity->id,
                'title' => $activity->title,
                'course_space_title' => $activity->courseSpace->title,
            ],
            'attempt' => [
                'id' => $attempt->id,
                'number' => $attempt->number(),
                'status' => $attempt->status->value,
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'final' => $attempt->status === AttemptStatus::Graded,
            ],
            'score' => $score,
            'answersVisible' => $answersVisible,
            'questions' => AttemptReview::questions($attempt, $answersVisible, $score !== null),
        ];
    }
}

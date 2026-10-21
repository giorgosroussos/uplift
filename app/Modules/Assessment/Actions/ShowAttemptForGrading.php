<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Support\AttemptReview;
use App\Modules\Assessment\Support\AttemptScore;

/**
 * UC-026 / UC-028: one attempt as the owning teacher grades it: the
 * student, every question with the answer given, the correct answers, the
 * points awarded and the feedback, and the score (provisional or final) on
 * the organisation's grading scale. Answers can be graded once the attempt
 * is submitted; an attempt whose time is up is submitted first
 * (SCOPE-024).
 */
class ShowAttemptForGrading
{
    public function __construct(private readonly SubmitQuizAttempt $submit) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(QuizAttempt $attempt): array
    {
        $this->submit->submitIfDue($attempt);

        $quiz = $attempt->quiz;
        $activity = $quiz->activity()->withTrashed()->firstOrFail();
        $inProgress = $attempt->status === AttemptStatus::InProgress;

        return [
            'quiz' => [
                'activity_id' => $activity->id,
                'title' => $activity->title,
            ],
            'attempt' => [
                'id' => $attempt->id,
                'number' => $attempt->number(),
                'student' => $attempt->student?->name,
                'status' => $attempt->status->value,
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'final' => $attempt->status === AttemptStatus::Graded,
            ],
            'score' => $inProgress ? null : AttemptScore::of($quiz, $attempt),
            'canGrade' => ! $inProgress,
            'questions' => AttemptReview::questions($attempt, true, true),
        ];
    }
}

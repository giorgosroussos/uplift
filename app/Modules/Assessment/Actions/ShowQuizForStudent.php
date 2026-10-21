<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Support\AttemptScore;
use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * UC-012: the quiz as a student sees it before and between attempts: its
 * rules, their own attempts with the scores they may see, and whether they
 * can start an attempt now. An attempt whose time is up is submitted first
 * (SCOPE-024). Only self-paced quizzes are taken in attempts (SCOPE-016).
 */
class ShowQuizForStudent
{
    public function __construct(private readonly SubmitQuizAttempt $submit) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $student, Activity $activity): array
    {
        $quiz = Quiz::forActivity($activity);

        if ($quiz === null || ! $quiz->isSelfPaced()) {
            throw new NotFoundHttpException;
        }

        $attempts = $quiz->attempts()
            ->where('student_id', $student->getKey())
            ->orderBy('started_at')
            ->get();

        foreach ($attempts as $attempt) {
            $this->submit->submitIfDue($attempt);
        }

        $inProgress = $attempts->first(fn (QuizAttempt $a): bool => $a->status === AttemptStatus::InProgress);
        $left = max(0, $quiz->max_attempts - $attempts->count());
        $deadline = $activity->deadline_at;
        $open = $activity->isPublished() && ($deadline === null || CarbonImmutable::now()->lessThan($deadline));

        return [
            'quiz' => [
                'activity_id' => $activity->id,
                'title' => $activity->title,
                'description' => $activity->description,
                'status' => $activity->status->value,
                'course_space_title' => $activity->courseSpace->title,
                'deadline_at' => $deadline?->toIso8601String(),
                'time_limit_minutes' => $quiz->time_limit === null ? null : intdiv($quiz->time_limit, 60),
                'max_attempts' => $quiz->max_attempts,
                'question_count' => $quiz->quizQuestions()->count(),
                'negative_marking' => $quiz->negative_marking,
                'negative_mark_fraction' => $quiz->negative_marking ? $quiz->negative_mark_fraction : null,
            ],
            'attempts' => $attempts->values()->map(fn (QuizAttempt $attempt, int $i): array => [
                'id' => $attempt->id,
                'number' => $i + 1,
                'status' => $attempt->status->value,
                'started_at' => $attempt->started_at->toIso8601String(),
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'score' => AttemptScore::visibleTo($quiz, $attempt),
            ])->all(),
            'attemptsLeft' => $left,
            'inProgressId' => $inProgress?->id,
            'canStart' => $open && $inProgress === null && $left > 0,
        ];
    }
}

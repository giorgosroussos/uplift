<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Activities\Enums\SubmissionStatus;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\Submission;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\QuizAttempt;

/**
 * UC-028: the submitted attempts of a quiz for the submissions overview:
 * student, submission time, status and raw points (the overview converts
 * nothing, DATA-003), with a link to grade the attempt. An attempt with
 * free-text answers waiting for grading needs grading; a graded attempt
 * (final score) is graded. Attempts in progress are not submissions yet,
 * and a quiz attempt is never merely "submitted".
 */
class ListQuizSubmissions
{
    /**
     * @return list<Submission>
     */
    public function handle(Activity $activity, ?SubmissionStatus $status): array
    {
        $statuses = match ($status) {
            null => [AttemptStatus::Submitted, AttemptStatus::Graded],
            SubmissionStatus::NeedsGrading => [AttemptStatus::Submitted],
            SubmissionStatus::Graded => [AttemptStatus::Graded],
            SubmissionStatus::Submitted => [],
        };

        if ($statuses === []) {
            return [];
        }

        return QuizAttempt::query()
            ->where('quiz_id', $activity->id)
            ->whereIn('status', array_map(static fn (AttemptStatus $s): string => $s->value, $statuses))
            ->whereNotNull('submitted_at')
            ->with(['student', 'group'])
            ->orderByDesc('submitted_at')
            ->get()
            ->map(fn (QuizAttempt $attempt): Submission => new Submission(
                id: $attempt->id,
                // SCOPE-012: a group attempt is the group's submission.
                participant: (string) ($attempt->isGroupAttempt() ? $attempt->group?->name : $attempt->student?->name),
                submittedAt: $attempt->submitted_at ?? $attempt->started_at,
                status: $attempt->status === AttemptStatus::Graded ? SubmissionStatus::Graded : SubmissionStatus::NeedsGrading,
                earnedPoints: $attempt->earned_points === null ? null : (string) $attempt->earned_points,
                maxPoints: $attempt->max_points === null ? null : (string) $attempt->max_points,
                url: route('assessment.grading.show', $attempt),
            ))
            ->values()
            ->all();
    }
}

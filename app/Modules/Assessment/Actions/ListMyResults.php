<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Support\AttemptScore;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * UC-017 step 1-2: My results. The student's own submitted quiz attempts
 * (OWN-006), grouped per activity with the most recent activity first:
 * each attempt's score on the organisation's grading scale (when the quiz
 * shows it), whether it is provisional or final, and whether the teacher
 * left feedback. Attempts whose time is up are submitted first
 * (SCOPE-024); attempts still in progress are not results yet.
 */
class ListMyResults
{
    public function __construct(
        private readonly SubmitQuizAttempt $submit,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(User $student): array
    {
        $attempts = QuizAttempt::query()
            ->where('student_id', $student->getKey())
            // Results of deleted activities are not listed.
            ->whereHas('quiz', fn (Builder $quiz) => $quiz->whereHas('activity'))
            ->with(['quiz.activity.courseSpace'])
            ->withCount(['answers as feedback_count' => fn (Builder $answers) => $answers->whereNotNull('feedback')])
            ->orderBy('started_at')
            ->get();

        foreach ($attempts as $attempt) {
            $this->submit->submitIfDue($attempt);
        }

        $scale = $this->tenant->current()?->grading_scale;

        /** @var Collection<string, Collection<int, QuizAttempt>> $byQuiz */
        $byQuiz = $attempts->groupBy('quiz_id');

        return $byQuiz
            ->map(function (Collection $quizAttempts) use ($scale): array {
                /** @var QuizAttempt $first */
                $first = $quizAttempts->first();
                $quiz = $first->quiz;
                $activity = $quiz->activity;

                $results = $quizAttempts->values()
                    ->map(fn (QuizAttempt $attempt, int $i): array => [
                        'id' => $attempt->id,
                        'number' => $i + 1,
                        'status' => $attempt->status->value,
                        'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                        'final' => $attempt->status === AttemptStatus::Graded,
                        'score' => AttemptScore::visibleTo($quiz, $attempt, $scale),
                        'has_feedback' => (int) $attempt->getAttribute('feedback_count') > 0,
                    ])
                    ->filter(fn (array $row): bool => $row['status'] !== AttemptStatus::InProgress->value)
                    ->values();

                return [
                    'activity_id' => $activity->id,
                    'title' => $activity->title,
                    'course_space_title' => $activity->courseSpace->title,
                    'last_submitted_at' => $results->max('submitted_at'),
                    'attempts' => $results->all(),
                ];
            })
            ->filter(fn (array $activity): bool => $activity['attempts'] !== [])
            ->sortByDesc('last_submitted_at')
            ->values()
            ->all();
    }
}

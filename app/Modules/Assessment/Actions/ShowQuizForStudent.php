<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Support\AttemptScore;
use App\Modules\Assessment\Support\GroupAttempts;
use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * UC-012: the quiz as a student sees it before and between attempts: its
 * rules, their own attempts with the scores they may see, and whether they
 * can start an attempt now. An attempt whose time is up is submitted first
 * (SCOPE-024). Only self-paced quizzes are taken in attempts (SCOPE-016).
 *
 * In a group quiz (SCOPE-012, SCOPE-025) the attempts are those of the
 * groups the student takes part in (OWN-006), and each of the student's
 * groups in the course space is offered with its attempts left and, when
 * it may not start an attempt now, the reason.
 */
class ShowQuizForStudent
{
    public function __construct(
        private readonly SubmitQuizAttempt $submit,
        private readonly GroupAttempts $groups,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $student, Activity $activity): array
    {
        $quiz = Quiz::forActivity($activity);

        if ($quiz === null || ! $quiz->isSelfPaced()) {
            throw new NotFoundHttpException;
        }

        $attempts = $activity->is_group
            ? GroupAttempts::whereTakesPart($quiz->attempts()->getQuery(), [(string) $student->getKey()])
                ->with('group')
                ->orderBy('started_at')
                ->get()
            : $quiz->attempts()
                ->where('student_id', $student->getKey())
                ->orderBy('started_at')
                ->get();

        foreach ($attempts as $attempt) {
            $this->submit->submitIfDue($attempt);
        }

        $inProgress = $attempts->first(fn (QuizAttempt $a): bool => $a->status === AttemptStatus::InProgress);
        $deadline = $activity->deadline_at;
        $open = $activity->isPublished() && ($deadline === null || CarbonImmutable::now()->lessThan($deadline));

        if ($activity->is_group) {
            $groups = $this->groupChoices($student, $activity, $quiz);
            $left = (int) max([0, ...array_map(fn (array $g): int => $g['attempts_left'], $groups)]);
            $canStart = $open && $inProgress === null
                && array_filter($groups, fn (array $g): bool => $g['refusal'] === null) !== [];
        } else {
            $groups = null;
            $left = max(0, $quiz->max_attempts - $attempts->count());
            $canStart = $open && $inProgress === null && $left > 0;
        }

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
                'is_group' => $activity->is_group,
                'group_size' => $activity->is_group ? $activity->group_size : null,
                'course_space_id' => $activity->course_space_id,
            ],
            'attempts' => $attempts->values()->map(fn (QuizAttempt $attempt, int $i): array => [
                'id' => $attempt->id,
                'number' => $attempt->isGroupAttempt() ? $attempt->number() : $i + 1,
                'group' => $attempt->group?->name,
                'status' => $attempt->status->value,
                'started_at' => $attempt->started_at->toIso8601String(),
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'score' => AttemptScore::visibleTo($quiz, $attempt),
            ])->all(),
            'attemptsLeft' => $left,
            'inProgressId' => $inProgress?->id,
            'canStart' => $canStart,
            'groups' => $groups,
        ];
    }

    /**
     * @return list<array{id: string, name: string, members: int, attempts_left: int, refusal: string|null}>
     */
    private function groupChoices(User $student, Activity $activity, Quiz $quiz): array
    {
        return $this->groups->actingGroups($student, $activity)
            ->map(function (Group $group) use ($activity, $quiz): array {
                $members = $this->groups->memberIds($group);

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'members' => count($members),
                    'attempts_left' => $this->groups->attemptsLeft($quiz, $group),
                    'refusal' => $this->groups->refusal($quiz, $activity, $group, $members),
                ];
            })
            ->values()
            ->all();
    }
}

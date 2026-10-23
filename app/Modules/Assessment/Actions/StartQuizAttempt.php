<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Support\GroupAttempts;
use App\Modules\Assessment\Support\Points;
use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-012 step 1 (SCOPE-024): a student starts an attempt of a published,
 * self-paced quiz before its deadline; the timer starts on the server.
 * ends_at is the earlier of started_at + time limit and the deadline, or
 * null without either. In-progress attempts count toward max_attempts,
 * and a new attempt is refused while one is in progress (the student
 * continues that one). An attempt whose time is up is submitted first.
 *
 * In a group quiz (SCOPE-012, SCOPE-025) the student picks which of their
 * groups the attempt belongs to; attempts, the attempt in progress and
 * max_attempts then count per group, the group's size is checked against
 * group_size, and a group is refused while one of its current members
 * takes part through another group (Support/GroupAttempts).
 */
class StartQuizAttempt
{
    public function __construct(
        private readonly SubmitQuizAttempt $submit,
        private readonly GroupAttempts $groups,
    ) {}

    /**
     * @param  string|null  $groupId  the acting group, in a group quiz
     *
     * @throws ValidationException (field "attempt", or "group_id" for an invalid group) when the attempt is refused
     */
    public function handle(User $student, Activity $activity, ?string $groupId = null): QuizAttempt
    {
        $group = $activity->is_group ? $this->actingGroup($student, $activity, $groupId) : null;

        foreach ($this->openAttempts($student, $activity, $group) as $open) {
            $this->submit->submitIfDue($open);
        }

        return DB::transaction(function () use ($student, $activity, $group): QuizAttempt {
            // Serialises concurrent starts of the quiz.
            $quiz = Quiz::query()->lockForUpdate()->find($activity->id);
            $now = CarbonImmutable::now();
            $deadline = $activity->deadline_at === null ? null : CarbonImmutable::instance($activity->deadline_at);

            if ($quiz === null || ! $quiz->isSelfPaced()) {
                // SCOPE-016: no self-paced attempts on a live quiz.
                $this->refuse('not_self_paced');
            }

            if (! $activity->isPublished()) {
                $this->refuse('not_open');
            }

            if ($deadline !== null && ! $now->lessThan($deadline)) {
                $this->refuse('deadline_passed');
            }

            if ($group !== null) {
                $this->checkGroup($student, $activity, $quiz, $group);
            } else {
                $attempts = $quiz->attempts()->where('student_id', $student->getKey());

                if ((clone $attempts)->where('status', AttemptStatus::InProgress->value)->exists()) {
                    $this->refuse('in_progress');
                }

                if ($attempts->count() >= $quiz->max_attempts) {
                    $this->refuse('no_attempts_left');
                }
            }

            $endsAt = $quiz->time_limit === null ? null : $now->addSeconds($quiz->time_limit);

            if ($deadline !== null && ($endsAt === null || $deadline->lessThan($endsAt))) {
                $endsAt = $deadline;
            }

            $attempt = new QuizAttempt(['started_at' => $now, 'ends_at' => $endsAt]);
            $attempt->organization_id = $quiz->organization_id;
            $attempt->quiz_id = $quiz->activity_id;
            if ($group !== null) {
                $attempt->group_id = $group->id;
            } else {
                $attempt->student_id = (string) $student->getKey();
            }

            $attempt->max_points = Points::format($this->maxPoints($quiz));
            $attempt->save();

            return $attempt;
        });
    }

    /**
     * The group the student picked: one of their groups in the activity's
     * course space.
     */
    private function actingGroup(User $student, Activity $activity, ?string $groupId): Group
    {
        $group = $groupId === null
            ? null
            : $this->groups->actingGroups($student, $activity)->firstWhere('id', $groupId);

        if ($group === null) {
            throw ValidationException::withMessages([
                'group_id' => (string) __('assessment.attempt.refused.choose_group'),
            ]);
        }

        return $group;
    }

    /**
     * Within the transaction: the group row is locked so its membership
     * cannot change meanwhile (Courses locks it for every change).
     */
    private function checkGroup(User $student, Activity $activity, Quiz $quiz, Group $group): void
    {
        $locked = Group::query()->whereKey($group->id)->lockForUpdate()->first();

        if ($locked === null || ! $locked->hasMember($student)) {
            throw ValidationException::withMessages([
                'group_id' => (string) __('assessment.attempt.refused.choose_group'),
            ]);
        }

        $reason = $this->groups->refusal($quiz, $activity, $locked, $this->groups->memberIds($locked));

        if ($reason !== null) {
            $this->refuse($reason);
        }
    }

    /**
     * @return iterable<QuizAttempt>
     */
    private function openAttempts(User $student, Activity $activity, ?Group $group): iterable
    {
        return QuizAttempt::query()
            ->where('quiz_id', $activity->id)
            ->when(
                $group === null,
                fn ($query) => $query->where('student_id', $student->getKey()),
                fn ($query) => $query->where('group_id', $group?->id),
            )
            ->where('status', AttemptStatus::InProgress->value)
            ->get();
    }

    /**
     * The sum of the points of the quiz's questions, in hundredths.
     */
    private function maxPoints(Quiz $quiz): int
    {
        $total = 0;

        foreach ($quiz->quizQuestions()->with('question')->get() as $placement) {
            $total += Points::toHundredths((string) $placement->question->points);
        }

        return $total;
    }

    private function refuse(string $reason): never
    {
        throw ValidationException::withMessages([
            'attempt' => (string) __("assessment.attempt.refused.{$reason}"),
        ]);
    }
}

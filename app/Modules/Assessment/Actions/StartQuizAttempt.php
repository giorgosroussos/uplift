<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Support\Points;
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
 */
class StartQuizAttempt
{
    public function __construct(private readonly SubmitQuizAttempt $submit) {}

    /**
     * @throws ValidationException (field "attempt") when the attempt is refused
     */
    public function handle(User $student, Activity $activity): QuizAttempt
    {
        foreach ($this->openAttempts($student, $activity) as $open) {
            $this->submit->submitIfDue($open);
        }

        return DB::transaction(function () use ($student, $activity): QuizAttempt {
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

            $attempts = $quiz->attempts()->where('student_id', $student->getKey());

            if ((clone $attempts)->where('status', AttemptStatus::InProgress->value)->exists()) {
                $this->refuse('in_progress');
            }

            if ($attempts->count() >= $quiz->max_attempts) {
                $this->refuse('no_attempts_left');
            }

            $endsAt = $quiz->time_limit === null ? null : $now->addSeconds($quiz->time_limit);

            if ($deadline !== null && ($endsAt === null || $deadline->lessThan($endsAt))) {
                $endsAt = $deadline;
            }

            $attempt = new QuizAttempt(['started_at' => $now, 'ends_at' => $endsAt]);
            $attempt->organization_id = $quiz->organization_id;
            $attempt->quiz_id = $quiz->activity_id;
            $attempt->student_id = (string) $student->getKey();
            $attempt->max_points = Points::format($this->maxPoints($quiz));
            $attempt->save();

            return $attempt;
        });
    }

    /**
     * @return iterable<QuizAttempt>
     */
    private function openAttempts(User $student, Activity $activity): iterable
    {
        return QuizAttempt::query()
            ->where('quiz_id', $activity->id)
            ->where('student_id', $student->getKey())
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

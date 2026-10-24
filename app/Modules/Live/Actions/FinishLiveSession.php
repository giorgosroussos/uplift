<?php

namespace App\Modules\Live\Actions;

use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Assessment\Support\Points;
use App\Modules\Live\Enums\LiveSessionStatus;
use App\Modules\Live\Events\LiveSessionFinished;
use App\Modules\Live\Models\LiveAnswer;
use App\Modules\Live\Models\LiveParticipant;
use App\Modules\Live\Models\LiveSession;
use App\Modules\Live\Support\LiveScorer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * UC-024 step 5 (SCOPE-021): finishes a running session at its common end
 * time, or as soon as every joined student has submitted, without any
 * action of the teacher. Participations not yet submitted are submitted
 * at the end time with the answers saved before it; answers are scored
 * with partial credit and no negative marking (LiveScorer, SCOPE-022);
 * each participant's score is the sum of their points and the final ranks
 * follow the scores (equal scores share a rank: 1, 1, 3). Answers saved
 * at or after the end time are not counted. Then running -> finished and
 * LiveSessionFinished (after commit). Safe to call any time and
 * concurrently: the session row is locked and a session that is not due
 * is left unchanged.
 */
class FinishLiveSession
{
    /**
     * @return bool whether the session finished now
     */
    public function handle(LiveSession $session): bool
    {
        return DB::transaction(function () use ($session): bool {
            $locked = LiveSession::query()->lockForUpdate()->find($session->id);

            if ($locked === null || ! $locked->isRunning()) {
                return false;
            }

            $now = CarbonImmutable::now();
            /** @var CarbonImmutable $endsAt */
            $endsAt = $locked->ends_at;

            if (! $locked->hasEnded($now) && ! self::allSubmitted($locked)) {
                return false;
            }

            $this->score($locked, $endsAt);

            $locked->transitionTo(LiveSessionStatus::Finished);
            $locked->finished_at = $now;
            $locked->save();

            LiveSessionFinished::dispatch($locked->organization_id, $locked->id, $locked->quiz_id);

            return true;
        });
    }

    /**
     * Whether at least one student joined and every joined student has submitted.
     */
    public static function allSubmitted(LiveSession $session): bool
    {
        $participants = LiveParticipant::query()->where('live_session_id', $session->id);

        return (clone $participants)->exists()
            && ! (clone $participants)->whereNull('submitted_at')->exists();
    }

    private function score(LiveSession $session, CarbonImmutable $endsAt): void
    {
        /** @var array<string, Question> $questions quiz question id => question */
        $questions = QuizQuestion::query()
            ->where('quiz_id', $session->quiz_id)
            ->with(['question.options', 'question.pairs'])
            ->get()
            ->mapWithKeys(fn (QuizQuestion $placement): array => [$placement->id => $placement->question])
            ->all();

        $participants = LiveParticipant::query()
            ->where('live_session_id', $session->id)
            ->with('answers')
            ->get();

        /** @var array<string, int> $scores participant id => hundredths */
        $scores = [];

        foreach ($participants as $participant) {
            $total = 0;

            foreach ($participant->answers as $answer) {
                [$points, $correct] = $this->scoreAnswer($answer, $questions[$answer->quiz_question_id] ?? null, $endsAt);
                $answer->points = Points::format($points);
                $answer->is_correct = $correct;
                $answer->save();
                $total += $points;
            }

            $scores[$participant->id] = $total;
        }

        $ranks = self::ranks($scores);

        foreach ($participants as $participant) {
            // Not submitted yet: submitted automatically at the end time.
            $participant->submitted_at ??= $endsAt;
            $participant->score = Points::format($scores[$participant->id]);
            $participant->final_rank = $ranks[$participant->id];
            $participant->save();
        }
    }

    /**
     * @return array{int, bool}
     */
    private function scoreAnswer(LiveAnswer $answer, ?Question $question, CarbonImmutable $endsAt): array
    {
        if ($question === null || $answer->answered_at->greaterThanOrEqualTo($endsAt)) {
            return [0, false];
        }

        return LiveScorer::score($answer, $question);
    }

    /**
     * Competition ranking: higher scores first, equal scores share a rank
     * and the next rank skips accordingly (1, 1, 3).
     *
     * @param  array<string, int>  $scores
     * @return array<string, int>
     */
    public static function ranks(array $scores): array
    {
        arsort($scores);

        $ranks = [];
        $position = 0;
        $rank = 0;
        $previous = null;

        foreach ($scores as $id => $score) {
            $position++;

            if ($score !== $previous) {
                $rank = $position;
                $previous = $score;
            }

            $ranks[$id] = $rank;
        }

        return $ranks;
    }
}

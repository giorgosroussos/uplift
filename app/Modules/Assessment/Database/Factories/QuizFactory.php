<?php

namespace App\Modules\Assessment\Database\Factories;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\LiveStartMode;
use App\Modules\Assessment\Enums\QuizMode;
use App\Modules\Assessment\Enums\ResultVisibility;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Courses\Models\CourseSpace;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Self-paced quizzes (the subtype row) of a new draft quiz activity unless
 * ->for($activity, 'activity') is given; ->live() and ->scheduled() make
 * live ones; ->withQuestions() places quiz copies owned by the course
 * space's teacher.
 *
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    protected $model = Quiz::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_id' => fn (): string => (string) Activity::factory()->create()->getKey(),
            'organization_id' => fn (array $attributes): string => Activity::query()
                ->withoutGlobalScopes()
                ->whereKey($attributes['activity_id'])
                ->value('organization_id'),
            'mode' => QuizMode::SelfPaced,
            'time_limit' => null,
            'max_attempts' => 1,
            'negative_marking' => false,
            'negative_mark_fraction' => null,
            'pass_mark_percentage' => null,
            'score_visibility' => ResultVisibility::Immediate,
            'answers_visibility' => ResultVisibility::AfterDeadline,
        ];
    }

    /**
     * A live quiz of 15 minutes started manually from a lobby.
     */
    public function live(): static
    {
        return $this->state([
            'mode' => QuizMode::Live,
            'time_limit' => 900,
            'live_start_mode' => LiveStartMode::Manual,
            'live_scheduled_start_at' => null,
        ]);
    }

    /**
     * A live quiz that starts by itself at the given time.
     */
    public function scheduled(DateTimeInterface $startsAt): static
    {
        return $this->live()->state([
            'live_start_mode' => LiveStartMode::Scheduled,
            'live_scheduled_start_at' => $startsAt,
        ]);
    }

    /**
     * Places the given number of single-choice quiz copies (or copies made
     * by the given question factory) in order.
     */
    public function withQuestions(int $count = 1, ?QuestionFactory $questions = null): static
    {
        return $this->afterCreating(function (Quiz $quiz) use ($count, $questions): void {
            $teacherId = CourseSpace::query()->withoutGlobalScopes()
                ->whereKey(Activity::query()->withoutGlobalScopes()->whereKey($quiz->activity_id)->value('course_space_id'))
                ->value('teacher_id');
            $start = $quiz->quizQuestions()->count();

            for ($i = 0; $i < $count; $i++) {
                $question = ($questions ?? Question::factory())->quizCopy()->create([
                    'owner_id' => $teacherId,
                    'organization_id' => $quiz->organization_id,
                ]);

                $placement = new QuizQuestion(['position' => $start + $i]);
                $placement->organization_id = $quiz->organization_id;
                $placement->quiz_id = $quiz->activity_id;
                $placement->question_id = $question->id;
                $placement->save();
            }
        });
    }
}

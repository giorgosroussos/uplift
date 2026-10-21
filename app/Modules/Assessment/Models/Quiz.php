<?php

namespace App\Modules\Assessment\Models;

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Database\Factories\QuizFactory;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\LiveStartMode;
use App\Modules\Assessment\Enums\QuizMode;
use App\Modules\Assessment\Enums\ResultVisibility;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ENT-Quiz: the quiz subtype of an activity (DATA-001), keyed by the
 * activity id. Written by the quiz's ActivityType (Support/QuizType) from
 * the wizard's settings step; its questions are ordered quiz copies
 * (ENT-QuizQuestion, DATA-008). Percentages are exact decimal strings
 * from 0 to 100 (DATA-003); the time limit is in seconds.
 *
 * @property string $activity_id
 * @property string $organization_id
 * @property QuizMode $mode
 * @property int|null $time_limit
 * @property LiveStartMode|null $live_start_mode
 * @property CarbonImmutable|null $live_scheduled_start_at
 * @property int $max_attempts
 * @property bool $negative_marking
 * @property string|null $negative_mark_fraction
 * @property string|null $pass_mark_percentage
 * @property ResultVisibility $score_visibility
 * @property ResultVisibility $answers_visibility
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Activity $activity
 * @property-read Collection<int, QuizQuestion> $quizQuestions
 * @property-read Collection<int, QuizAttempt> $attempts
 */
#[UseFactory(QuizFactory::class)]
class Quiz extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<QuizFactory> */
    use HasFactory;

    protected $primaryKey = 'activity_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'mode',
        'time_limit',
        'max_attempts',
        'negative_marking',
        'negative_mark_fraction',
        'pass_mark_percentage',
        'score_visibility',
        'answers_visibility',
    ];

    public static function forActivity(Activity $activity): ?self
    {
        return self::query()->find($activity->id);
    }

    public function isSelfPaced(): bool
    {
        return $this->mode === QuizMode::SelfPaced;
    }

    /**
     * Whether the student who made the attempt may see its score now.
     */
    public function isScoreVisible(QuizAttempt $attempt, ?CarbonImmutable $now = null): bool
    {
        return $this->resultVisible($this->score_visibility, $attempt, $now);
    }

    /**
     * Whether the student who made the attempt may see the correct answers now.
     */
    public function areAnswersVisible(QuizAttempt $attempt, ?CarbonImmutable $now = null): bool
    {
        return $this->resultVisible($this->answers_visibility, $attempt, $now);
    }

    /**
     * Results are shown only after the attempt is submitted. after_deadline
     * waits for the activity's deadline (server time, DATA-004); on a quiz
     * without a deadline it means immediately after submission (SCOPE-023).
     */
    private function resultVisible(ResultVisibility $visibility, QuizAttempt $attempt, ?CarbonImmutable $now): bool
    {
        if ($attempt->status === AttemptStatus::InProgress || $visibility === ResultVisibility::Hidden) {
            return false;
        }

        if ($visibility === ResultVisibility::Immediate) {
            return true;
        }

        $deadline = $this->activity->deadline_at;

        return $deadline === null || ($now ?? CarbonImmutable::now())->greaterThanOrEqualTo($deadline);
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    /**
     * @return HasMany<QuizQuestion, $this>
     */
    public function quizQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class, 'quiz_id')->orderBy('position');
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'quiz_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => QuizMode::class,
            'time_limit' => 'integer',
            'live_start_mode' => LiveStartMode::class,
            'live_scheduled_start_at' => 'immutable_datetime',
            'max_attempts' => 'integer',
            'negative_marking' => 'boolean',
            'negative_mark_fraction' => 'decimal:2',
            'pass_mark_percentage' => 'decimal:2',
            'score_visibility' => ResultVisibility::class,
            'answers_visibility' => ResultVisibility::class,
        ];
    }
}

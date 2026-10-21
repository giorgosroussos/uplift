<?php

namespace App\Modules\Assessment\Models;

use App\Modules\Assessment\Database\Factories\QuizAttemptFactory;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\GradingStatus;
use App\Modules\Assessment\Exceptions\InvalidStateTransition;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ENT-QuizAttempt: one attempt of a self-paced quiz. Access follows OWN-006
 * (Policies/QuizAttemptPolicy). The status follows the state model
 * (in_progress -> submitted -> graded); any other change is rejected and
 * leaves the status unchanged. earned_points is personal data and never
 * goes into logs or error messages (SEC-003).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $quiz_id
 * @property string|null $student_id
 * @property string|null $group_id
 * @property string|null $submitted_by
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $submitted_at
 * @property string|null $earned_points
 * @property string|null $max_points
 * @property AttemptStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Quiz $quiz
 * @property-read User|null $student
 * @property-read Collection<int, Answer> $answers
 */
#[UseFactory(QuizAttemptFactory::class)]
class QuizAttempt extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<QuizAttemptFactory> */
    use HasFactory;

    use HasUuids;

    private const string ENTITY = 'A quiz attempt';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'started_at',
        'ends_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'earned_points',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'in_progress',
    ];

    protected static function booted(): void
    {
        static::creating(function (QuizAttempt $attempt): void {
            if ($attempt->status !== AttemptStatus::initial()) {
                throw InvalidStateTransition::initial(self::ENTITY, AttemptStatus::initial()->value, $attempt->status->value);
            }
        });

        static::updating(function (QuizAttempt $attempt): void {
            if (! $attempt->isDirty('status')) {
                return;
            }

            $from = AttemptStatus::from((string) $attempt->getRawOriginal('status'));
            $to = $attempt->status;

            if (! $from->canTransitionTo($to)) {
                $attempt->status = $from;

                throw InvalidStateTransition::between(self::ENTITY, $from->value, $to->value);
            }
        });
    }

    /**
     * Moves the attempt to another status and saves it.
     *
     * @throws InvalidStateTransition when the state model does not allow it
     */
    public function transitionTo(AttemptStatus $to): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw InvalidStateTransition::between(self::ENTITY, $this->status->value, $to->value);
        }

        $this->status = $to;
        $this->save();
    }

    /**
     * In progress but past ends_at (server time, DATA-004): it must be
     * submitted before anything else happens to it (SCOPE-024).
     */
    public function isDue(?CarbonImmutable $now = null): bool
    {
        return $this->status === AttemptStatus::InProgress
            && $this->ends_at !== null
            && ! ($now ?? CarbonImmutable::now())->lessThan($this->ends_at);
    }

    /**
     * The attempt's ordinal among its student's attempts of the quiz (1 = first).
     */
    public function number(): int
    {
        return self::query()
            ->where('quiz_id', $this->quiz_id)
            ->where('student_id', $this->student_id)
            ->where('started_at', '<=', $this->started_at)
            ->count();
    }

    /**
     * Whether any answer of the attempt still waits for grading (SCOPE-024).
     */
    public function hasPendingAnswers(): bool
    {
        return $this->answers()->where('grading_status', GradingStatus::Pending->value)->exists();
    }

    public function isOf(User $student): bool
    {
        return $this->student_id !== null && $this->student_id === $student->getKey();
    }

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return HasMany<Answer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'attempt_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'started_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'earned_points' => 'decimal:2',
            'max_points' => 'decimal:2',
        ];
    }
}

<?php

namespace App\Modules\Assessment\Models;

use App\Modules\Assessment\Database\Factories\AnswerFactory;
use App\Modules\Assessment\Enums\GradingStatus;
use App\Modules\Assessment\Exceptions\InvalidStateTransition;
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
 * ENT-Answer: the answer to one quiz question in an attempt: selected
 * options, matches or a text. Access follows OWN-007
 * (Policies/AnswerPolicy). The grading status follows the state model
 * (initial pending; pending -> graded); any other change is rejected and
 * leaves the status unchanged. text_response and feedback are personal
 * data and never go into logs or error messages (SEC-003). Never
 * soft-deleted (DATA-007).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $attempt_id
 * @property string $quiz_question_id
 * @property string|null $text_response
 * @property string|null $points_awarded
 * @property GradingStatus $grading_status
 * @property string|null $feedback
 * @property string|null $graded_by
 * @property CarbonImmutable|null $graded_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read QuizAttempt $attempt
 * @property-read QuizQuestion $quizQuestion
 * @property-read Collection<int, AnswerSelection> $selections
 * @property-read Collection<int, AnswerMatch> $matches
 */
#[UseFactory(AnswerFactory::class)]
class Answer extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<AnswerFactory> */
    use HasFactory;

    use HasUuids;

    private const string ENTITY = 'An answer';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'text_response',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'text_response',
        'feedback',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'grading_status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::creating(function (Answer $answer): void {
            if ($answer->grading_status !== GradingStatus::initial()) {
                throw InvalidStateTransition::initial(self::ENTITY, GradingStatus::initial()->value, $answer->grading_status->value);
            }
        });

        static::updating(function (Answer $answer): void {
            if (! $answer->isDirty('grading_status')) {
                return;
            }

            $from = GradingStatus::from((string) $answer->getRawOriginal('grading_status'));
            $to = $answer->grading_status;

            if (! $from->canTransitionTo($to)) {
                $answer->grading_status = $from;

                throw InvalidStateTransition::between(self::ENTITY, $from->value, $to->value);
            }
        });
    }

    /**
     * Moves the answer to another grading status and saves it.
     *
     * @throws InvalidStateTransition when the state model does not allow it
     */
    public function transitionTo(GradingStatus $to): void
    {
        if (! $this->grading_status->canTransitionTo($to)) {
            throw InvalidStateTransition::between(self::ENTITY, $this->grading_status->value, $to->value);
        }

        $this->grading_status = $to;
        $this->save();
    }

    /**
     * @return BelongsTo<QuizAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }

    /**
     * @return BelongsTo<QuizQuestion, $this>
     */
    public function quizQuestion(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class);
    }

    /**
     * @return HasMany<AnswerSelection, $this>
     */
    public function selections(): HasMany
    {
        return $this->hasMany(AnswerSelection::class);
    }

    /**
     * @return HasMany<AnswerMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(AnswerMatch::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grading_status' => GradingStatus::class,
            'points_awarded' => 'decimal:2',
            'graded_at' => 'immutable_datetime',
        ];
    }
}

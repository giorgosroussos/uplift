<?php

namespace App\Modules\Live\Models;

use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Live\Database\Factories\LiveAnswerFactory;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ENT-LiveAnswer: the current answer of a participant to one question; it
 * may change until the participant submits or the end time passes and is
 * scored when the session finishes (is_correct and points stay null
 * before, SCOPE-021). Access follows OWN-013 (Policies/LiveAnswerPolicy).
 * response is personal data and never goes into logs or error messages
 * (SEC-003). Never soft-deleted (DATA-007).
 *
 * response holds `option_ids` (choice questions) or `matches`, a list of
 * {left_pair_id, right_pair_id} (matching questions).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $live_participant_id
 * @property string $quiz_question_id
 * @property array<string, mixed> $response
 * @property bool|null $is_correct
 * @property string|null $points
 * @property CarbonImmutable $answered_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read LiveParticipant $participant
 * @property-read QuizQuestion $quizQuestion
 */
#[UseFactory(LiveAnswerFactory::class)]
class LiveAnswer extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<LiveAnswerFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'response',
        'is_correct',
        'points',
    ];

    /**
     * @return BelongsTo<LiveParticipant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(LiveParticipant::class, 'live_participant_id');
    }

    /**
     * @return BelongsTo<QuizQuestion, $this>
     */
    public function quizQuestion(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response' => 'array',
            'is_correct' => 'boolean',
            'points' => 'decimal:2',
            'answered_at' => 'immutable_datetime',
        ];
    }
}

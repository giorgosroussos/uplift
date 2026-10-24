<?php

namespace App\Modules\Live\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Live\Database\Factories\LiveParticipantFactory;
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
 * ENT-LiveParticipant: a student in a live session. Access follows OWN-012
 * (Policies/LiveParticipantPolicy). score is personal data and never goes
 * into logs or error messages (SEC-003); it and final_rank are set when
 * the session finishes.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $live_session_id
 * @property string $student_id
 * @property CarbonImmutable $joined_at
 * @property CarbonImmutable|null $submitted_at
 * @property string|null $score
 * @property int|null $final_rank
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read LiveSession $session
 * @property-read User $student
 * @property-read Collection<int, LiveAnswer> $answers
 */
#[UseFactory(LiveParticipantFactory::class)]
class LiveParticipant extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<LiveParticipantFactory> */
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
        'score',
    ];

    public function hasSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isOf(User $student): bool
    {
        return $this->student_id === $student->getKey();
    }

    /**
     * @return BelongsTo<LiveSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class, 'live_session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return HasMany<LiveAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(LiveAnswer::class, 'live_participant_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'score' => 'decimal:2',
            'final_rank' => 'integer',
        ];
    }
}

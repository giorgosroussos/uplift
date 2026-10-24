<?php

namespace App\Modules\Live\Models;

use App\Modules\Assessment\Models\Quiz;
use App\Modules\Identity\Models\User;
use App\Modules\Live\Database\Factories\LiveSessionFactory;
use App\Modules\Live\Enums\LiveSessionStatus;
use App\Modules\Live\Exceptions\InvalidLiveSessionTransition;
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
 * ENT-LiveSession: the real-time run of a live quiz with one common end
 * time (SCOPE-021), fixed at its start as started_at + the quiz duration
 * and enforced on the server. At most one non-cancelled session per quiz
 * (SCOPE-022, partial unique index). The status follows the state model
 * (LiveSessionStatus); any other change is rejected and leaves the status
 * unchanged.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $quiz_id
 * @property string $host_id
 * @property LiveSessionStatus $status
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Quiz $quiz
 * @property-read User $host
 * @property-read Collection<int, LiveParticipant> $participants
 */
#[UseFactory(LiveSessionFactory::class)]
class LiveSession extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<LiveSessionFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'lobby',
    ];

    protected static function booted(): void
    {
        static::creating(function (LiveSession $session): void {
            if ($session->status !== LiveSessionStatus::initial()) {
                throw InvalidLiveSessionTransition::initial($session->status);
            }
        });

        static::updating(function (LiveSession $session): void {
            if (! $session->isDirty('status')) {
                return;
            }

            $from = LiveSessionStatus::from((string) $session->getRawOriginal('status'));
            $to = $session->status;

            if (! $from->canTransitionTo($to)) {
                $session->status = $from;

                throw InvalidLiveSessionTransition::between($from, $to);
            }
        });
    }

    /**
     * Moves the session to another status (without saving).
     *
     * @throws InvalidLiveSessionTransition when the state model does not allow it
     */
    public function transitionTo(LiveSessionStatus $to): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw InvalidLiveSessionTransition::between($this->status, $to);
        }

        $this->status = $to;
    }

    public function isLobby(): bool
    {
        return $this->status === LiveSessionStatus::Lobby;
    }

    public function isRunning(): bool
    {
        return $this->status === LiveSessionStatus::Running;
    }

    public function isFinished(): bool
    {
        return $this->status === LiveSessionStatus::Finished;
    }

    /**
     * Whether the common end time has passed (server time).
     */
    public function hasEnded(?CarbonImmutable $now = null): bool
    {
        return $this->ends_at !== null && ($now ?? CarbonImmutable::now())->greaterThanOrEqualTo($this->ends_at);
    }

    /**
     * Whether answers are taken now: running and before the end time.
     */
    public function takesAnswers(?CarbonImmutable $now = null): bool
    {
        return $this->isRunning() && ! $this->hasEnded($now);
    }

    /**
     * Seconds left until the common end time, null before the start.
     */
    public function remainingSeconds(?CarbonImmutable $now = null): ?int
    {
        if ($this->ends_at === null) {
            return null;
        }

        return max(0, (int) ($now ?? CarbonImmutable::now())->diffInSeconds($this->ends_at, false));
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
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /**
     * @return HasMany<LiveParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(LiveParticipant::class, 'live_session_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LiveSessionStatus::class,
            'started_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}

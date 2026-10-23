<?php

namespace App\Modules\Activities\Models;

use App\Modules\Activities\Database\Factories\PollFactory;
use App\Modules\Activities\Enums\PollResultsVisibility;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * ENT-Poll: the poll subtype of an activity (DATA-001), keyed by the
 * activity id. Anonymous (SCOPE-013): who voted is recorded in
 * ENT-PollParticipation, what was voted in ENT-PollVote, and the two are
 * never linked (DATA-006).
 *
 * A poll is open while its activity is published and its deadline (if
 * any) has not passed, by the server's clock (DATA-004); it is closed once
 * the activity is closed or the deadline has passed.
 *
 * @property string $activity_id
 * @property string $organization_id
 * @property int $max_choices
 * @property PollResultsVisibility $results_visibility
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Activity $activity
 * @property-read Collection<int, PollOption> $options
 * @property-read Collection<int, PollParticipation> $participations
 */
#[UseFactory(PollFactory::class)]
class Poll extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<PollFactory> */
    use HasFactory;

    protected $primaryKey = 'activity_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'max_choices',
        'results_visibility',
    ];

    public static function forActivity(Activity $activity): ?self
    {
        return self::query()->find($activity->id);
    }

    public function isOpen(?CarbonImmutable $now = null): bool
    {
        $deadline = $this->activity->deadline_at;

        return $this->activity->isPublished()
            && ($deadline === null || ($now ?? CarbonImmutable::now())->lessThan($deadline));
    }

    public function isClosed(?CarbonImmutable $now = null): bool
    {
        return ! $this->activity->isDraft() && ! $this->isOpen($now);
    }

    /**
     * SCOPE-013: a student sees the results once the poll is closed, and
     * with results "after vote" also as soon as they have voted.
     */
    public function resultsVisibleToStudent(bool $hasVoted, ?CarbonImmutable $now = null): bool
    {
        if ($this->isClosed($now)) {
            return true;
        }

        return $hasVoted && $this->results_visibility === PollResultsVisibility::AfterVote;
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    /**
     * @return HasMany<PollOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class, 'poll_id')->orderBy('position');
    }

    /**
     * @return HasMany<PollParticipation, $this>
     */
    public function participations(): HasMany
    {
        return $this->hasMany(PollParticipation::class, 'poll_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_choices' => 'integer',
            'results_visibility' => PollResultsVisibility::class,
        ];
    }
}

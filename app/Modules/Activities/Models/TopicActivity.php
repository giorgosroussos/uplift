<?php

namespace App\Modules\Activities\Models;

use App\Modules\Activities\Database\Factories\TopicActivityFactory;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * ENT-TopicActivity: the subtype of a topic selection or a topic proposal
 * activity (DATA-001), keyed by the activity id. A topic selection offers
 * topics with a capacity (SCOPE-011); a student, or in a group activity a
 * group, holds at most one of them. A topic proposal receives the topics
 * students (or groups) propose (UC-015).
 *
 * Choices and proposals can be made while the activity is published and
 * its deadline (if any) has not passed, by the server's clock (DATA-004).
 *
 * @property string $activity_id
 * @property string $organization_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Activity $activity
 * @property-read Collection<int, Topic> $topics
 */
#[UseFactory(TopicActivityFactory::class)]
class TopicActivity extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<TopicActivityFactory> */
    use HasFactory;

    protected $primaryKey = 'activity_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public static function forActivity(Activity $activity): ?self
    {
        return self::query()->find($activity->id);
    }

    /**
     * The subtype row of the activity, or an unsaved one when nothing has
     * created it yet (a topic proposal activity gets its row with its first
     * proposal).
     */
    public static function forActivityOrNew(Activity $activity): self
    {
        $topicActivity = self::forActivity($activity);

        if ($topicActivity === null) {
            $topicActivity = new self;
            $topicActivity->activity_id = $activity->id;
            $topicActivity->organization_id = $activity->organization_id;
        }

        return $topicActivity->setRelation('activity', $activity);
    }

    public function isOpen(?CarbonImmutable $now = null): bool
    {
        $deadline = $this->activity->deadline_at;

        return $this->activity->isPublished()
            && ($deadline === null || ($now ?? CarbonImmutable::now())->lessThan($deadline));
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    /**
     * The topics in alphabetical order.
     *
     * @return HasMany<Topic, $this>
     */
    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class, 'topic_activity_id')->orderBy('title')->orderBy('id');
    }

    /**
     * The proposals of a topic proposal activity, oldest first.
     *
     * @return HasMany<TopicProposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(TopicProposal::class, 'topic_activity_id')->orderBy('created_at')->orderBy('id');
    }

    /**
     * Every current choice of the activity.
     *
     * @return Builder<TopicChoice>
     */
    public function choices(): Builder
    {
        return TopicChoice::query()->whereIn(
            'topic_id',
            Topic::query()->select('id')->where('topic_activity_id', $this->activity_id),
        );
    }
}

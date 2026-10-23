<?php

namespace App\Modules\Activities\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * ENT-Topic: a topic offered for selection, with the number of holders it
 * takes (SCOPE-011).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $topic_activity_id
 * @property string $title
 * @property string|null $description
 * @property int $capacity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TopicActivity $topicActivity
 */
class Topic extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'capacity',
    ];

    /**
     * @return BelongsTo<TopicActivity, $this>
     */
    public function topicActivity(): BelongsTo
    {
        return $this->belongsTo(TopicActivity::class, 'topic_activity_id');
    }

    /**
     * @return HasMany<TopicChoice, $this>
     */
    public function choices(): HasMany
    {
        return $this->hasMany(TopicChoice::class, 'topic_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }
}

<?php

namespace App\Modules\Activities\Models;

use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ENT-TopicChoice: the current topic of a student (individual activity)
 * or of a group (group activity, SCOPE-012); one per holder and activity.
 * Written only by SelectTopic and CancelTopicChoice. OWN-008
 * (TopicChoicePolicy): a student changes only their own or their group's
 * choice; a teacher sees choices only of their own activities.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $topic_id
 * @property string|null $student_id
 * @property string|null $group_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Topic $topic
 * @property-read User|null $student
 * @property-read Group|null $group
 */
class TopicChoice extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    /**
     * @return BelongsTo<Topic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * The group, also once it is deleted: its choices stay as history (SCOPE-025).
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id')->withTrashed();
    }
}

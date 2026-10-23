<?php

namespace App\Modules\Activities\Models;

use App\Modules\Activities\Enums\TopicProposalStatus;
use App\Modules\Activities\Exceptions\InvalidTopicProposalTransition;
use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ENT-TopicProposal: a topic proposed by a student (individual activity)
 * or by one member for a group (group activity, SCOPE-012) in a topic
 * proposal activity (UC-015). Created pending by ProposeTopic; decided
 * once, approved or rejected with the teacher's comment, by
 * DecideTopicProposal (UC-027). OWN-009 (TopicProposalPolicy): a student
 * reads only their own or their group's proposals; a teacher decides only
 * proposals of their own activities.
 *
 * The description is personal data (SEC-003) and never goes to logs or
 * error messages.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $topic_activity_id
 * @property string|null $student_id
 * @property string|null $group_id
 * @property string $title
 * @property string|null $description
 * @property TopicProposalStatus $status
 * @property string|null $teacher_comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TopicActivity $topicActivity
 * @property-read User|null $student
 * @property-read Group|null $group
 */
class TopicProposal extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'description',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::creating(function (TopicProposal $proposal): void {
            if ($proposal->status !== TopicProposalStatus::initial()) {
                throw InvalidTopicProposalTransition::initial($proposal->status);
            }
        });

        static::updating(function (TopicProposal $proposal): void {
            if (! $proposal->isDirty('status')) {
                return;
            }

            $from = TopicProposalStatus::from((string) $proposal->getRawOriginal('status'));
            $to = $proposal->status;

            if (! $from->canTransitionTo($to)) {
                $proposal->status = $from;

                throw InvalidTopicProposalTransition::between($from, $to);
            }
        });
    }

    /**
     * Decides the proposal and saves it, with the teacher's comment.
     *
     * @throws InvalidTopicProposalTransition when the state model does not allow it
     */
    public function transitionTo(TopicProposalStatus $to, ?string $comment = null): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw InvalidTopicProposalTransition::between($this->status, $to);
        }

        $this->status = $to;
        $this->teacher_comment = $comment;
        $this->save();
    }

    public function isPending(): bool
    {
        return $this->status === TopicProposalStatus::Pending;
    }

    /**
     * @return BelongsTo<TopicActivity, $this>
     */
    public function topicActivity(): BelongsTo
    {
        return $this->belongsTo(TopicActivity::class, 'topic_activity_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * The group, also once it is deleted: its proposals stay as history (SCOPE-025).
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TopicProposalStatus::class,
        ];
    }
}

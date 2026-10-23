<?php

namespace App\Modules\Courses\Models;

use App\Modules\Courses\Database\Factories\GroupMembershipFactory;
use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Exceptions\InvalidGroupMembershipTransition;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ENT-GroupMembership: a membership or pending invitation of a student in a
 * group (SCOPE-025). A new row per invitation; old rows stay unchanged as
 * history. Never soft-deleted. Tenant-owned; access follows OWN-005.
 *
 * The status follows the state model of the design; any other change is
 * rejected and leaves the status unchanged. A row is created as `invited`,
 * except the creator's own membership, which is created as `member`.
 * joined_at is set on becoming a member, left_at on leaving.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $group_id
 * @property string $student_id
 * @property GroupMembershipStatus $status
 * @property string|null $invited_by
 * @property CarbonImmutable|null $joined_at
 * @property CarbonImmutable|null $left_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Group $group
 * @property-read User $student
 */
#[UseFactory(GroupMembershipFactory::class)]
class GroupMembership extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<GroupMembershipFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'invited',
    ];

    protected static function booted(): void
    {
        static::creating(function (GroupMembership $membership): void {
            $status = $membership->status;

            if ($status === GroupMembershipStatus::Member && $membership->isCreatorRow()) {
                $membership->joined_at ??= now();

                return;
            }

            if ($status !== GroupMembershipStatus::initial()) {
                throw InvalidGroupMembershipTransition::initial($status);
            }
        });

        static::updating(function (GroupMembership $membership): void {
            if (! $membership->isDirty('status')) {
                return;
            }

            /** @var GroupMembershipStatus $from */
            $from = $membership->getOriginal('status');
            $to = $membership->status;

            if (! $from->canTransitionTo($to)) {
                $membership->status = $from;

                throw InvalidGroupMembershipTransition::between($from, $to);
            }
        });
    }

    /**
     * Moves the row to another status, stamps joined_at or left_at, and
     * saves it.
     *
     * @throws InvalidGroupMembershipTransition when the state model does not allow it
     */
    public function transitionTo(GroupMembershipStatus $to): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw InvalidGroupMembershipTransition::between($this->status, $to);
        }

        $this->status = $to;

        if ($to === GroupMembershipStatus::Member) {
            $this->joined_at = now();
        }

        if ($to === GroupMembershipStatus::Left) {
            $this->left_at = now();
        }

        $this->save();
    }

    public function isInvited(): bool
    {
        return $this->status === GroupMembershipStatus::Invited;
    }

    public function isMember(): bool
    {
        return $this->status === GroupMembershipStatus::Member;
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Whether this row is the creator's first membership of a new group:
     * the creator, and the group has no rows yet.
     */
    private function isCreatorRow(): bool
    {
        $createdBy = Group::query()->withoutGlobalScopes()->whereKey($this->group_id)->value('created_by');

        return $createdBy !== null
            && $createdBy === $this->student_id
            && ! self::query()->withoutGlobalScopes()->where('group_id', $this->group_id)->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GroupMembershipStatus::class,
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }
}

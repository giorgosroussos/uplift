<?php

namespace App\Modules\Courses\Models;

use App\Modules\Courses\Database\Factories\GroupFactory;
use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ENT-Group: a student group in a course space, used for group activities
 * (SCOPE-025). Tenant-owned and soft-deleted when its owner dissolves it or
 * it has no members left; a deleted group is absent for everybody, while
 * its memberships stay as history. Access follows OWN-005
 * (Policies/GroupPolicy).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $course_space_id
 * @property string $name
 * @property string $created_by
 * @property string $owner_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read CourseSpace $courseSpace
 * @property-read User $owner
 */
#[UseFactory(GroupFactory::class)]
class Group extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->getKey()
            && $this->organization_id === $user->organization_id;
    }

    public function hasMember(User $user): bool
    {
        return $this->hasOpenRow($user, GroupMembershipStatus::Member);
    }

    public function hasInvited(User $user): bool
    {
        return $this->hasOpenRow($user, GroupMembershipStatus::Invited);
    }

    /**
     * @return BelongsTo<CourseSpace, $this>
     */
    public function courseSpace(): BelongsTo
    {
        return $this->belongsTo(CourseSpace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Every membership row, history included.
     *
     * @return HasMany<GroupMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMembership::class);
    }

    private function hasOpenRow(User $user, GroupMembershipStatus $status): bool
    {
        return $this->organization_id === $user->organization_id
            && $this->memberships()
                ->where('student_id', $user->getKey())
                ->where('status', $status)
                ->exists();
    }
}

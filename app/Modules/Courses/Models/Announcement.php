<?php

namespace App\Modules\Courses\Models;

use App\Modules\Courses\Database\Factories\AnnouncementFactory;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ENT-Announcement: an announcement posted in a course space (UC-008).
 * Tenant-owned and soft-deleted (DATA-007); access follows
 * Policies/AnnouncementPolicy (the owning teacher writes, the owning teacher
 * and enrolled students read).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $course_space_id
 * @property string $author_id
 * @property string $title
 * @property string $body
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read CourseSpace $courseSpace
 * @property-read User $author
 */
#[UseFactory(AnnouncementFactory::class)]
class Announcement extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'body',
    ];

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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }
}

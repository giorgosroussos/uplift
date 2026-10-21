<?php

namespace App\Modules\Activities\Models;

use App\Modules\Activities\Database\Factories\ActivityFactory;
use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\ActivityStatus;
use App\Modules\Activities\Exceptions\InvalidActivityTransition;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * ENT-Activity: the base row of every activity, of one of the five types;
 * the type's module keeps one subtype row with the same id (DATA-001).
 * Tenant-owned: queries only see activities of the current tenant.
 *
 * The status follows the state model of the design (draft -> published ->
 * closed); any other change of status is rejected and leaves the status
 * unchanged.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $course_space_id
 * @property ActivityKind $type
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $deadline_at
 * @property bool $is_group
 * @property int|null $group_size
 * @property ActivityStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read CourseSpace $courseSpace
 */
#[UseFactory(ActivityFactory::class)]
class Activity extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'deadline_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'is_group' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (Activity $activity): void {
            if ($activity->status !== ActivityStatus::initial()) {
                throw InvalidActivityTransition::initial($activity->status);
            }
        });

        static::updating(function (Activity $activity): void {
            if (! $activity->isDirty('status')) {
                return;
            }

            $from = ActivityStatus::from((string) $activity->getRawOriginal('status'));
            $to = $activity->status;

            if (! $from->canTransitionTo($to)) {
                $activity->status = $from;

                throw InvalidActivityTransition::between($from, $to);
            }
        });
    }

    /**
     * Moves the activity to another status and saves it; publishing stamps
     * published_at (UTC, server time).
     *
     * @throws InvalidActivityTransition when the state model does not allow it
     */
    public function transitionTo(ActivityStatus $to): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw InvalidActivityTransition::between($this->status, $to);
        }

        $this->status = $to;

        if ($to === ActivityStatus::Published) {
            $this->published_at = Carbon::now();
        }

        $this->save();
    }

    public function isDraft(): bool
    {
        return $this->status === ActivityStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === ActivityStatus::Published;
    }

    public function isClosed(): bool
    {
        return $this->status === ActivityStatus::Closed;
    }

    /**
     * @return BelongsTo<CourseSpace, $this>
     */
    public function courseSpace(): BelongsTo
    {
        return $this->belongsTo(CourseSpace::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityKind::class,
            'status' => ActivityStatus::class,
            'deadline_at' => 'datetime',
            'published_at' => 'datetime',
            'is_group' => 'boolean',
            'group_size' => 'integer',
        ];
    }
}

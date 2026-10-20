<?php

namespace App\Modules\Courses\Models;

use App\Modules\Courses\Database\Factories\CourseSpaceFactory;
use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Enums\EnrolmentPolicy;
use App\Modules\Courses\Exceptions\InvalidCourseSpaceTransition;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * ENT-CourseSpace: a course space owned by exactly one teacher (SCOPE-007).
 * Tenant-owned: queries only see course spaces of the current tenant.
 *
 * The status follows the state model of the design; any other change of
 * status is rejected and leaves the status unchanged. The enrolment key is
 * hidden from serialisation: only the owning teacher is shown it.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $teacher_id
 * @property string $title
 * @property string|null $description
 * @property EnrolmentPolicy $enrolment_policy
 * @property string|null $enrolment_key
 * @property CourseSpaceStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $teacher
 */
#[UseFactory(CourseSpaceFactory::class)]
class CourseSpace extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<CourseSpaceFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'enrolment_policy',
        'enrolment_key',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'enrolment_key',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    protected static function booted(): void
    {
        static::saving(function (CourseSpace $space): void {
            // An open space keeps no key.
            if ($space->enrolment_policy === EnrolmentPolicy::Open) {
                $space->enrolment_key = null;
            }
        });

        static::creating(function (CourseSpace $space): void {
            if ($space->status !== CourseSpaceStatus::initial()) {
                throw InvalidCourseSpaceTransition::initial($space->status);
            }
        });

        static::updating(function (CourseSpace $space): void {
            if (! $space->isDirty('status')) {
                return;
            }

            /** @var CourseSpaceStatus $from */
            $from = $space->getOriginal('status');
            $to = $space->status;

            if (! $from->canTransitionTo($to)) {
                $space->status = $from;

                throw InvalidCourseSpaceTransition::between($from, $to);
            }
        });
    }

    /**
     * Moves the course space to another status and saves it.
     *
     * @throws InvalidCourseSpaceTransition when the state model does not allow it
     */
    public function transitionTo(CourseSpaceStatus $to): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw InvalidCourseSpaceTransition::between($this->status, $to);
        }

        $this->status = $to;
        $this->save();
    }

    public function isActive(): bool
    {
        return $this->status === CourseSpaceStatus::Active;
    }

    public function isArchived(): bool
    {
        return $this->status === CourseSpaceStatus::Archived;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->teacher_id === $user->getKey()
            && $this->organization_id === $user->organization_id;
    }

    public function hasEnrolled(User $student): bool
    {
        return $this->organization_id === $student->organization_id
            && $this->enrolments()->where('student_id', $student->getKey())->exists();
    }

    /**
     * Whether the given key opens this space. Open spaces need no key.
     */
    public function acceptsKey(#[\SensitiveParameter] ?string $key): bool
    {
        if ($this->enrolment_policy === EnrolmentPolicy::Open) {
            return true;
        }

        return $key !== null
            && $this->enrolment_key !== null
            && hash_equals($this->enrolment_key, trim($key));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return HasMany<Enrolment, $this>
     */
    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CourseSpaceStatus::class,
            'enrolment_policy' => EnrolmentPolicy::class,
        ];
    }
}

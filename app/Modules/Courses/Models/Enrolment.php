<?php

namespace App\Modules\Courses\Models;

use App\Modules\Courses\Database\Factories\EnrolmentFactory;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ENT-Enrolment: a student's enrolment in a course space, unique per pair.
 * Tenant-owned; access follows OWN-003 (Policies/EnrolmentPolicy).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $course_space_id
 * @property string $student_id
 * @property Carbon $enrolled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CourseSpace $courseSpace
 * @property-read User $student
 */
#[UseFactory(EnrolmentFactory::class)]
class Enrolment extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<EnrolmentFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_space_id',
        'student_id',
        'enrolled_at',
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
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
        ];
    }
}

<?php

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\Database\Factories\OrganizationFactory;
use App\Modules\Tenancy\Enums\GradingScale;
use App\Modules\Tenancy\Enums\Locale;
use App\Modules\Tenancy\Enums\OrganizationStatus;
use App\Modules\Tenancy\Exceptions\InvalidOrganizationTransition;
use App\Modules\Tenancy\Support\Subdomain;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ENT-Organization: a tenant (school, university, tutoring centre or company).
 *
 * The status follows the state model of the design; any other change of
 * status is rejected and leaves the status unchanged.
 *
 * @property string $id
 * @property string $name
 * @property string $subdomain
 * @property OrganizationStatus $status
 * @property string|null $logo_path
 * @property string|null $accent_color
 * @property string $student_term_el
 * @property string $student_term_en
 * @property string $teacher_term_el
 * @property string $teacher_term_en
 * @property GradingScale $grading_scale
 * @property bool $leaderboard_enabled
 * @property Locale $default_locale
 */
#[UseFactory(OrganizationFactory::class)]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'subdomain',
        'logo_path',
        'accent_color',
        'student_term_el',
        'student_term_en',
        'teacher_term_el',
        'teacher_term_en',
        'grading_scale',
        'leaderboard_enabled',
        'default_locale',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            if ($organization->status !== OrganizationStatus::initial()) {
                throw InvalidOrganizationTransition::initial($organization->status);
            }
        });

        static::updating(function (Organization $organization): void {
            if (! $organization->isDirty('status')) {
                return;
            }

            /** @var OrganizationStatus $from */
            $from = $organization->getOriginal('status');
            $to = $organization->status;

            if (! $from->canTransitionTo($to)) {
                $organization->status = $from;

                throw InvalidOrganizationTransition::between($from, $to);
            }
        });
    }

    /**
     * Moves the organisation to another status and saves it.
     *
     * @throws InvalidOrganizationTransition when the state model does not allow it
     */
    public function transitionTo(OrganizationStatus $to): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw InvalidOrganizationTransition::between($this->status, $to);
        }

        $this->status = $to;
        $this->save();
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::Active;
    }

    public function isSuspended(): bool
    {
        return $this->status === OrganizationStatus::Suspended;
    }

    /**
     * Subdomains are case-insensitive; they are stored in lower case.
     *
     * @return Attribute<string, string>
     */
    protected function subdomain(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Subdomain::normalize($value),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'grading_scale' => GradingScale::class,
            'default_locale' => Locale::class,
            'leaderboard_enabled' => 'boolean',
        ];
    }
}

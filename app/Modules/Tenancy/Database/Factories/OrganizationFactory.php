<?php

namespace App\Modules\Tenancy\Database\Factories;

use App\Modules\Tenancy\Enums\GradingScale;
use App\Modules\Tenancy\Enums\Locale;
use App\Modules\Tenancy\Enums\OrganizationStatus;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'subdomain' => 'org-'.fake()->unique()->lexify('????????'),
            'student_term_el' => 'Μαθητής',
            'student_term_en' => 'Student',
            'teacher_term_el' => 'Καθηγητής',
            'teacher_term_en' => 'Teacher',
            'grading_scale' => GradingScale::Scale0To10,
            'leaderboard_enabled' => true,
            // English, like the application default: tests that expect a
            // Greek UI set it explicitly (DATA-011 applies default_locale).
            'default_locale' => Locale::English,
        ];
    }

    /**
     * Created active (the initial state), then suspended through the state model.
     */
    public function suspended(): static
    {
        return $this->afterCreating(
            fn (Organization $organization) => $organization->transitionTo(OrganizationStatus::Suspended),
        );
    }
}

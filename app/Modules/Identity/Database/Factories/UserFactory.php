<?php

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Users of the current tenant, or of a new organisation when there is none.
 * Use ->for($organization, 'organization') for a specific organisation.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The password every factory user has unless given another one.
     */
    public const string PASSWORD = 'correct-horse-battery';

    protected static ?string $passwordHash = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => fn (): string => app(TenantContext::class)->id()
                ?? (string) Organization::factory()->create()->getKey(),
            'role' => Role::Student,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$passwordHash ??= Hash::make(self::PASSWORD),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function student(): static
    {
        return $this->state(['role' => Role::Student]);
    }

    public function teacher(): static
    {
        return $this->state(['role' => Role::Teacher]);
    }

    public function orgAdmin(): static
    {
        return $this->state(['role' => Role::OrgAdmin]);
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withoutPassword(): static
    {
        return $this->state(['password' => null]);
    }
}

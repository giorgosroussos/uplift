<?php

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Enums\InvitationStatus;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pending invitations of the current tenant (or of the organisation given
 * with ->for($organization, 'organization')), invited by an org admin of the
 * same organisation. The plain token of the last made invitation is in
 * InvitationFactory::$lastToken.
 *
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public static ?string $lastToken = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::Student,
            'expires_at' => now()->addDays(Invitation::VALID_DAYS),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Invitation $invitation): void {
            self::$lastToken = $invitation->issueToken();

            if ($invitation->getAttribute('invited_by') === null) {
                $admin = User::factory()->orgAdmin();

                if ($invitation->getAttribute('organization_id') !== null) {
                    $admin = $admin->state(['organization_id' => $invitation->organization_id]);
                }

                $invitation->invited_by = (string) $admin->create()->getKey();
            }
        });
    }

    public function role(Role $role): static
    {
        return $this->state(['role' => $role]);
    }

    /**
     * Sent the given number of days ago (its link expired after 7 days).
     */
    public function sentDaysAgo(int $days): static
    {
        return $this->state(fn (): array => [
            'created_at' => now()->subDays($days),
            'updated_at' => now()->subDays($days),
            'expires_at' => now()->subDays($days)->addDays(Invitation::VALID_DAYS),
        ]);
    }

    /**
     * Moved to a final status after creation (the state model only allows
     * creating pending invitations).
     */
    public function inStatus(InvitationStatus $status): static
    {
        return $this->afterCreating(function (Invitation $invitation) use ($status): void {
            $invitation->transitionTo($status);
        });
    }
}

<?php

namespace App\Modules\Courses\Database\Factories;

use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pending invitations of the current tenant. Use ->for($group, 'group')
 * and ->for($student, 'student') for specific ones.
 *
 * @extends Factory<GroupMembership>
 */
class GroupMembershipFactory extends Factory
{
    protected $model = GroupMembership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => fn (): string => (string) Group::factory()->create()->getKey(),
            'organization_id' => fn (array $attributes): string => Group::query()
                ->withoutGlobalScopes()
                ->whereKey($attributes['group_id'])
                ->value('organization_id'),
            'student_id' => fn (array $attributes): string => (string) User::factory()->student()->create([
                'organization_id' => $attributes['organization_id'],
            ])->getKey(),
            'status' => 'invited',
        ];
    }
}

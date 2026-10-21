<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Support\OrganizationDates;
use App\Modules\Tenancy\Enums\OrganizationStatus;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\OrganizationScope;

/**
 * UC-035: key platform metrics across all organisations: organisations by
 * status and users by role, plus the newest organisations.
 *
 * Works across tenants on purpose (platform admins only, SEC-006); it reads
 * only counts of users, never their personal data.
 */
final class GetPlatformMetrics
{
    public const int RECENT = 5;

    /**
     * @return array{
     *     organizations: array{total: int, active: int, suspended: int},
     *     users: array{total: int, active: int, student: int, teacher: int, org_admin: int},
     *     recent_organizations: list<array{id: string, name: string, subdomain: string, status: string, created_at: string|null}>
     * }
     */
    public function handle(): array
    {
        /** @var array<string, int> $byStatus */
        $byStatus = Organization::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        $users = User::query()->withoutGlobalScope(OrganizationScope::class);

        /** @var array<string, int> $byRole */
        $byRole = (clone $users)
            ->selectRaw('role, count(*) as aggregate')
            ->groupBy('role')
            ->pluck('aggregate', 'role')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        $recent = Organization::query()
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->limit(self::RECENT)
            ->get()
            ->map(fn (Organization $organization): array => [
                'id' => $organization->id,
                'name' => $organization->name,
                'subdomain' => $organization->subdomain,
                'status' => $organization->status->value,
                'created_at' => OrganizationDates::createdAt($organization),
            ])
            ->values()
            ->all();

        return [
            'organizations' => [
                'total' => array_sum($byStatus),
                'active' => $byStatus[OrganizationStatus::Active->value] ?? 0,
                'suspended' => $byStatus[OrganizationStatus::Suspended->value] ?? 0,
            ],
            'users' => [
                'total' => array_sum($byRole),
                'active' => (clone $users)->where('is_active', true)->count(),
                'student' => $byRole[Role::Student->value] ?? 0,
                'teacher' => $byRole[Role::Teacher->value] ?? 0,
                'org_admin' => $byRole[Role::OrgAdmin->value] ?? 0,
            ],
            'recent_organizations' => $recent,
        ];
    }
}

<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Support\OrganizationDates;
use App\Modules\Tenancy\Enums\OrganizationStatus;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\OrganizationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * UC-036 step 1: all organisations of the platform with their status and
 * number of users (search by name or subdomain, filter by status, paginated).
 *
 * Works across tenants on purpose (platform admins only, SEC-006); it reads
 * only counts of users, never their personal data.
 */
final class ListOrganizations
{
    public const int PER_PAGE = 25;

    /**
     * @param  array{search?: string|null, status?: string|null}  $filters  validated filters
     * @return LengthAwarePaginator<int, array{id: string, name: string, subdomain: string, status: string, users_count: int, created_at: string|null}>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = OrganizationStatus::tryFrom((string) ($filters['status'] ?? ''));

        $usersCount = User::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->selectRaw('count(*)')
            ->whereColumn('users.organization_id', 'organizations.id');

        return Organization::query()
            ->select('organizations.*')
            ->selectSub($usersCount->toBase(), 'users_count')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes(mb_strtolower($search), '\\%_').'%';
                $query->where(fn (Builder $query) => $query
                    ->whereRaw('lower(name) like ?', [$like])
                    ->orWhere('subdomain', 'like', $like));
            })
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status->value))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Organization $organization): array => [
                'id' => $organization->id,
                'name' => $organization->name,
                'subdomain' => $organization->subdomain,
                'status' => $organization->status->value,
                'users_count' => (int) $organization->getAttribute('users_count'),
                'created_at' => OrganizationDates::createdAt($organization),
            ]);
    }
}

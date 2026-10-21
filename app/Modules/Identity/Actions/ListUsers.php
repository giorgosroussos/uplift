<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\InvitationStatus;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * UC-032 step 1: the user list of the current organisation (search, filter
 * by role and status, paginated) and its pending and expired invitations,
 * which can be resent (DATA-015). Tenant-scoped:
 * users and invitations of other organisations never appear.
 */
final class ListUsers
{
    public const int PER_PAGE = 25;

    /**
     * @param  array{search?: string|null, role?: string|null, status?: string|null}  $filters  validated filters
     * @return array{users: LengthAwarePaginator<int, array{id: string, name: string, nickname: string, email: string, role: string, is_active: bool}>, invitations: Collection<int, array{id: string, email: string, role: value-of<Role>, expires_at: string, expired: bool, email_failed: bool, revocable: bool}>}
     */
    public function handle(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $role = Role::tryFrom((string) ($filters['role'] ?? ''));
        $status = $filters['status'] ?? null;

        $users = User::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes(mb_strtolower($search), '\\%_').'%';
                $query->where(fn (Builder $query) => $query
                    ->whereRaw('lower(name) like ?', [$like])
                    ->orWhereRaw('lower(nickname) like ?', [$like])
                    ->orWhere('email', 'like', $like));
            })
            ->when($role !== null, fn (Builder $query) => $query->where('role', $role->value))
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'nickname' => $user->nickname,
                'email' => $user->email,
                'role' => $user->role->value,
                'is_active' => $user->is_active,
            ]);

        $invitations = Invitation::query()
            ->whereIn('status', [InvitationStatus::Pending->value, InvitationStatus::Expired->value])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Invitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'expired' => $invitation->status === InvitationStatus::Expired || $invitation->hasExpired(),
                'email_failed' => $invitation->emailFailed(),
                'revocable' => $invitation->isPending(),
            ]);

        return ['users' => $users, 'invitations' => $invitations];
    }
}

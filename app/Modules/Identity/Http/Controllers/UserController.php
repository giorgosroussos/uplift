<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\ChangeUserRole;
use App\Modules\Identity\Actions\DeactivateUser;
use App\Modules\Identity\Actions\ListUsers;
use App\Modules\Identity\Actions\ReactivateUser;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Http\Requests\ChangeUserRoleRequest;
use App\Modules\Identity\Http\Requests\ListUsersRequest;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-032: the org admin's user management (OWN-001: only users of the
 * admin's own organisation; others are not even found).
 */
class UserController extends Controller
{
    public function index(ListUsersRequest $request, ListUsers $listUsers): Response
    {
        $filters = $request->filters();

        return Inertia::render('Identity/Users', [
            ...$listUsers->handle($filters),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'roles' => array_map(fn (Role $role): string => $role->value, Role::cases()),
        ]);
    }

    public function updateRole(ChangeUserRoleRequest $request, User $user, ChangeUserRole $changeUserRole): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $changeUserRole->handle($actor, $user, $request->role());

        return $this->done('users.flash.role_changed');
    }

    public function deactivate(Request $request, User $user, DeactivateUser $deactivateUser): RedirectResponse
    {
        Gate::authorize('manage', $user);

        /** @var User $actor */
        $actor = $request->user();
        $deactivateUser->handle($actor, $user);

        return $this->done('users.flash.deactivated');
    }

    public function reactivate(Request $request, User $user, ReactivateUser $reactivateUser): RedirectResponse
    {
        Gate::authorize('manage', $user);

        /** @var User $actor */
        $actor = $request->user();
        $reactivateUser->handle($actor, $user);

        return $this->done('users.flash.reactivated');
    }

    private function done(string $messageKey): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => (string) __($messageKey)]);

        return back();
    }
}

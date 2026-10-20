<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\InviteUser;
use App\Modules\Identity\Actions\RevokeInvitation;
use App\Modules\Identity\Http\Requests\InviteUserRequest;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * UC-032: an org admin invites users and revokes pending invitations of
 * their own organisation (OWN-002).
 */
class InvitationController extends Controller
{
    public function store(InviteUserRequest $request, InviteUser $inviteUser): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $inviteUser->handle($admin, $request->email(), $request->role());

        Inertia::flash('toast', ['type' => 'success', 'message' => (string) __('users.flash.invited')]);

        return back();
    }

    public function revoke(Request $request, Invitation $invitation, RevokeInvitation $revokeInvitation): RedirectResponse
    {
        Gate::authorize('revoke', $invitation);

        /** @var User $actor */
        $actor = $request->user();
        $revokeInvitation->handle($actor, $invitation);

        Inertia::flash('toast', ['type' => 'success', 'message' => (string) __('users.flash.revoked')]);

        return back();
    }
}

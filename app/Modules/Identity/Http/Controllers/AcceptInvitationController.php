<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\AcceptInvitation;
use App\Modules\Identity\Actions\OpenInvitation;
use App\Modules\Identity\Http\Requests\AcceptInvitationRequest;
use App\Modules\Identity\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-005: the invitee opens the link from the invitation email on the
 * organisation's subdomain, sees the organisation and role, and sets name
 * and password. Expired, revoked or accepted invitations are rejected
 * (InvitationUnavailable renders the reason).
 */
class AcceptInvitationController extends Controller
{
    public function show(Invitation $invitation, string $token, OpenInvitation $openInvitation): Response
    {
        $invitation = $openInvitation->handle($invitation, $token);

        return Inertia::render('auth/AcceptInvitation', [
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'organization' => $invitation->organization->name,
            ],
            'token' => $token,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(AcceptInvitationRequest $request, Invitation $invitation, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $acceptInvitation->handle($invitation, $token, $request->account());

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}

<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\InvitationStatus;
use App\Modules\Identity\Events\InvitationRevoked;
use App\Modules\Identity\Exceptions\InvalidInvitationTransition;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-032: an org admin revokes a pending invitation of their organisation
 * (OWN-002); its link stops working.
 */
final class RevokeInvitation
{
    /**
     * @throws ValidationException when the invitation is no longer pending
     */
    public function handle(User $actor, Invitation $invitation): void
    {
        try {
            DB::transaction(function () use ($invitation): void {
                /** @var Invitation $locked */
                $locked = Invitation::query()->lockForUpdate()->findOrFail($invitation->getKey());
                $locked->transitionTo(InvitationStatus::Revoked);
            });
        } catch (InvalidInvitationTransition) {
            throw ValidationException::withMessages(['invitation' => (string) __('users.errors.not_pending')]);
        }

        InvitationRevoked::dispatch($invitation->organization_id, (string) $invitation->getKey(), (string) $actor->getKey());
    }
}

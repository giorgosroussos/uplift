<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Exceptions\InvitationUnavailable;
use App\Modules\Identity\Models\Invitation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * UC-005 step 1-2: the invitee opens the link. A wrong token is not found;
 * an invitation past its validity is recorded as expired; an expired,
 * revoked or accepted invitation is rejected.
 */
final class OpenInvitation
{
    /**
     * @throws NotFoundHttpException when the token does not match
     * @throws InvitationUnavailable when the invitation is no longer pending
     */
    public function handle(Invitation $invitation, #[\SensitiveParameter] string $token): Invitation
    {
        if (! $invitation->matchesToken($token)) {
            throw new NotFoundHttpException;
        }

        if (! $invitation->expireIfDue()) {
            throw new InvitationUnavailable($invitation->status);
        }

        return $invitation;
    }
}

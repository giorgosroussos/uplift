<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\InvitationStatus;
use App\Modules\Identity\Exceptions\InvalidInvitationTransition;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Support\EmailLock;
use App\Modules\Identity\Support\InvitationMail;
use App\Modules\Identity\Support\PlatformEmails;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DATA-015: an org admin of the invitation's organisation resends a pending
 * or expired invitation. A new token replaces the old one (the old link
 * stops working), the link is valid for 7 days again, the invitation is
 * pending, email_failed_at is cleared and one invitation email is queued.
 * Accepted and revoked invitations cannot be resent.
 */
final class ResendInvitation
{
    /**
     * @throws ValidationException when the invitation is accepted or revoked,
     *                             or its email was taken or invited again in
     *                             the meantime
     */
    public function handle(Invitation $invitation): Invitation
    {
        [$locked, $token] = DB::transaction(function () use ($invitation): array {
            /** @var Invitation $locked */
            $locked = Invitation::query()->lockForUpdate()->findOrFail($invitation->getKey());

            if (! in_array($locked->status, [InvitationStatus::Pending, InvitationStatus::Expired], true)) {
                throw ValidationException::withMessages(['invitation' => (string) __('users.errors.not_resendable')]);
            }

            EmailLock::acquire($locked->email);

            if (PlatformEmails::inUse($locked->email)) {
                throw ValidationException::withMessages(['invitation' => (string) __('users.errors.email_taken')]);
            }

            $invitedAgain = Invitation::query()
                ->whereKeyNot($locked->getKey())
                ->where('email', $locked->email)
                ->where('status', InvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->exists();

            if ($invitedAgain) {
                throw ValidationException::withMessages(['invitation' => (string) __('users.errors.already_invited')]);
            }

            try {
                $token = $locked->reissue();
            } catch (InvalidInvitationTransition) {
                throw ValidationException::withMessages(['invitation' => (string) __('users.errors.not_resendable')]);
            }

            return [$locked, $token];
        });

        InvitationMail::queue($locked, $locked->organization, $token);

        return $locked;
    }
}

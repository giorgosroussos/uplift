<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\InvitationStatus;
use App\Modules\Identity\Events\InvitationAccepted;
use App\Modules\Identity\Exceptions\InvitationUnavailable;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\EmailLock;
use App\Modules\Identity\Support\PlatformEmails;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * UC-005 step 3: creates the invitee's account in the inviting organisation
 * with the invited role and a verified email, marks the invitation accepted
 * (single use) and logs the new user in.
 */
final class AcceptInvitation
{
    public function __construct(private readonly OpenInvitation $openInvitation) {}

    /**
     * @param  array{name: string, password: string}  $input  validated input
     *
     * @throws NotFoundHttpException when the token does not match
     * @throws InvitationUnavailable when the invitation is no longer pending
     * @throws ValidationException when the email was taken in the meantime
     */
    public function handle(Invitation $invitation, #[\SensitiveParameter] string $token, array $input): User
    {
        // Outside the transaction, so that an expiry found here is recorded.
        $this->openInvitation->handle($invitation, $token);

        /** @var User $user */
        $user = DB::transaction(function () use ($invitation, $token, $input): User {
            /** @var Invitation $locked */
            $locked = Invitation::query()->lockForUpdate()->findOrFail($invitation->getKey());

            $this->openInvitation->handle($locked, $token);

            EmailLock::acquire($locked->email);

            if (PlatformEmails::inUse($locked->email)) {
                throw ValidationException::withMessages(['invitation' => (string) __('invitations.email_taken')]);
            }

            $user = new User([
                'role' => $locked->role,
                'name' => $input['name'],
                'email' => $locked->email,
                'password' => $input['password'],
            ]);
            $user->forceFill([
                'organization_id' => $locked->organization_id,
                'email_verified_at' => now(),
                'is_active' => true,
            ])->save();

            $locked->transitionTo(InvitationStatus::Accepted);

            return $user;
        });

        Auth::guard('web')->login($user);

        InvitationAccepted::dispatch($user->organization_id, (string) $invitation->getKey(), (string) $user->getKey());

        return $user;
    }
}

<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\InvitationStatus;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Events\InvitationCreated;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\InvitationNotification;
use App\Modules\Identity\Support\EmailLock;
use App\Modules\Identity\Support\OrganizationUrl;
use App\Modules\Identity\Support\PlatformEmails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * UC-032 step 2: an org admin invites an email address with a role. The
 * address must not belong to any user of the platform (SEC-004) nor have a
 * valid pending invitation of the organisation. After commit the invitation
 * email is queued and InvitationCreated is dispatched.
 */
final class InviteUser
{
    /**
     * @throws ValidationException when the email is taken or already invited
     */
    public function handle(User $admin, string $email, Role $role): Invitation
    {
        $email = mb_strtolower(trim($email));

        [$invitation, $token] = DB::transaction(function () use ($admin, $email, $role): array {
            EmailLock::acquire($email);

            if (PlatformEmails::inUse($email)) {
                throw ValidationException::withMessages(['email' => (string) __('users.errors.email_taken')]);
            }

            $alreadyInvited = Invitation::query()
                ->where('email', $email)
                ->where('status', InvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->exists();

            if ($alreadyInvited) {
                throw ValidationException::withMessages(['email' => (string) __('users.errors.already_invited')]);
            }

            $invitation = new Invitation([
                'email' => $email,
                'role' => $role,
                'invited_by' => $admin->getKey(),
                'expires_at' => now()->addDays(Invitation::VALID_DAYS),
            ]);
            $token = $invitation->issueToken();
            $invitation->save();

            return [$invitation, $token];
        });

        $organization = $admin->organization;
        $url = OrganizationUrl::for($organization)->route('invitation.show', [
            'invitation' => $invitation->getKey(),
            'token' => $token,
        ]);

        Notification::route('mail', $invitation->email)
            ->notify((new InvitationNotification(
                (string) $invitation->getKey(),
                $url,
                $organization->name,
                $invitation->role->value,
                Invitation::VALID_DAYS,
            ))->locale($organization->default_locale->value));

        InvitationCreated::dispatch($invitation->organization_id, (string) $invitation->getKey(), (string) $admin->getKey());

        return $invitation;
    }
}

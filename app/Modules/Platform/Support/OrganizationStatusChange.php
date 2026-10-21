<?php

namespace App\Modules\Platform\Support;

use App\Modules\Tenancy\Enums\OrganizationStatus;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-036: moves an organisation to another status through the state model of
 * ENT-Organization (`active -> suspended`, `suspended -> active`). Any other
 * transition is rejected and leaves the status unchanged.
 */
final class OrganizationStatusChange
{
    /**
     * Locks the organisation row so that two admins cannot change it at once.
     *
     * @throws ValidationException when the state model does not allow the transition
     */
    public static function apply(Organization $organization, OrganizationStatus $to): void
    {
        DB::transaction(function () use ($organization, $to): void {
            /** @var Organization $locked */
            $locked = Organization::query()->lockForUpdate()->findOrFail($organization->getKey());

            if (! $locked->status->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'organization' => (string) __('platform.organizations.errors.not_allowed.'.$locked->status->value),
                ]);
            }

            $locked->transitionTo($to);
        });

        $organization->refresh();
    }
}

<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Support\GroupLifecycle;
use Illuminate\Support\Facades\DB;

/**
 * UC-019: the owner dissolves the group. It is soft-deleted and its pending
 * invitations become withdrawn; its attempts, topic choices, proposals and
 * bookings stay untouched (SCOPE-025).
 */
class DissolveGroup
{
    public function __construct(private readonly GroupLifecycle $lifecycle) {}

    public function handle(Group $group): void
    {
        DB::transaction(function () use ($group): void {
            $locked = $this->lifecycle->lock($group->id);

            if ($locked !== null) {
                $this->lifecycle->delete($locked, releaseMembers: false);
            }
        });
    }
}

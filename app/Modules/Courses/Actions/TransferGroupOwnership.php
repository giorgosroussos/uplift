<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Support\GroupLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-019: the owner hands ownership to another current member (SCOPE-025).
 */
class TransferGroupOwnership
{
    public function __construct(private readonly GroupLifecycle $lifecycle) {}

    public function handle(Group $group, string $studentId): Group
    {
        DB::transaction(function () use ($group, $studentId): void {
            $locked = $this->lifecycle->lock($group->id);

            $isMember = $locked !== null
                && $studentId !== $locked->owner_id
                && $locked->memberships()
                    ->where('student_id', $studentId)
                    ->where('status', GroupMembershipStatus::Member)
                    ->exists();

            if (! $isMember) {
                throw ValidationException::withMessages(['student_id' => __('groups.errors.not_a_member')]);
            }

            $locked->owner_id = $studentId;
            $locked->save();
        });

        return $group->refresh();
    }
}

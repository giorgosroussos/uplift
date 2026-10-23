<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Models\Group;

/**
 * UC-019: the owner renames the group (SCOPE-025).
 */
class RenameGroup
{
    public function handle(Group $group, string $name): Group
    {
        $group->name = $name;
        $group->save();

        return $group;
    }
}

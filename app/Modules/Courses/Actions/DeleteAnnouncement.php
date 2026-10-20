<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Models\Announcement;

/**
 * UC-008: the owning teacher deletes an announcement; it is soft-deleted
 * (DATA-007) and no longer shown.
 */
class DeleteAnnouncement
{
    public function handle(Announcement $announcement): void
    {
        $announcement->delete();
    }
}

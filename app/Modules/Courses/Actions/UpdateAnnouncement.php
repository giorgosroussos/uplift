<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Models\Announcement;

/**
 * UC-008: the owning teacher corrects the title or body of an announcement.
 * The publication time stays; no new notification is sent.
 */
class UpdateAnnouncement
{
    /**
     * @param  array{title: string, body: string}  $input
     */
    public function handle(Announcement $announcement, array $input): Announcement
    {
        $announcement->fill($input)->save();

        return $announcement;
    }
}

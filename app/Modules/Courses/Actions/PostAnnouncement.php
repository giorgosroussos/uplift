<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Events\AnnouncementPublished;
use App\Modules\Courses\Models\Announcement;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * UC-008: the owning teacher posts an announcement in a course space. It is
 * published at once; AnnouncementPublished is dispatched after commit.
 */
class PostAnnouncement
{
    /**
     * @param  array{title: string, body: string}  $input
     */
    public function handle(User $author, CourseSpace $space, array $input): Announcement
    {
        $announcement = new Announcement($input);
        $announcement->organization_id = $space->organization_id;
        $announcement->course_space_id = $space->id;
        $announcement->author_id = (string) $author->getKey();
        $announcement->published_at = now();

        DB::transaction(fn () => $announcement->save());

        AnnouncementPublished::dispatch(
            $announcement->organization_id,
            $announcement->course_space_id,
            $announcement->id,
            $announcement->author_id,
        );

        return $announcement;
    }
}

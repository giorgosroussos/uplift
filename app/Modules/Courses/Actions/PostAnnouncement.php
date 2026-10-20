<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Events\AnnouncementPublished;
use App\Modules\Courses\Models\Announcement;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * UC-008 and DATA-013: the owning teacher posts an announcement in a course
 * space. It is published at once (no drafts, no scheduling). Only a post in
 * an active space dispatches AnnouncementPublished, after commit; a post in
 * an archived space is stored and notifies nobody.
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

        DB::transaction(function () use ($space, $announcement): void {
            // Read the status under a row lock so a concurrent archive cannot
            // slip between the check and the commit.
            $active = CourseSpace::query()
                ->whereKey($space->getKey())
                ->lockForUpdate()
                ->firstOrFail()
                ->isActive();

            $announcement->save();

            if ($active) {
                // ShouldDispatchAfterCommit: delivered only once committed.
                AnnouncementPublished::dispatch(
                    $announcement->organization_id,
                    $announcement->course_space_id,
                    $announcement->id,
                    $announcement->author_id,
                );
            }
        });

        return $announcement;
    }
}

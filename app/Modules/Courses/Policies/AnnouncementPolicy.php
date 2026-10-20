<?php

namespace App\Modules\Courses\Policies;

use App\Modules\Courses\Models\Announcement;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * UC-008 and SCOPE-007: only the owning teacher of the course space edits
 * or deletes its announcements; the owning teacher and the enrolled students
 * read them. Follows the course space abilities of CourseSpacePolicy.
 */
class AnnouncementPolicy
{
    public function view(User $actor, Announcement $announcement): bool
    {
        return $this->allows($actor, 'viewAnnouncements', $announcement);
    }

    public function update(User $actor, Announcement $announcement): bool
    {
        return $this->allows($actor, 'manageAnnouncements', $announcement);
    }

    public function delete(User $actor, Announcement $announcement): bool
    {
        return $this->allows($actor, 'manageAnnouncements', $announcement);
    }

    private function allows(User $actor, string $ability, Announcement $announcement): bool
    {
        /** @var CourseSpace|null $space */
        $space = $announcement->courseSpace;

        return $space !== null
            && $actor->organization_id === $announcement->organization_id
            && Gate::forUser($actor)->allows($ability, $space);
    }
}

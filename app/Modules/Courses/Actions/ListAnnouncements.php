<?php

namespace App\Modules\Courses\Actions;

use App\Modules\Courses\Models\Announcement;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * UC-008: the published announcements of a course space, newest first, for
 * a user allowed to read them; nothing for anyone else.
 */
class ListAnnouncements
{
    /**
     * @return list<array{id: string, title: string, body: string, author: string, published_at: string|null}>
     */
    public function handle(User $user, CourseSpace $space): array
    {
        if (Gate::forUser($user)->denies('viewAnnouncements', $space)) {
            return [];
        }

        return Announcement::query()
            ->where('course_space_id', $space->id)
            ->whereNotNull('published_at')
            ->with('author:id,organization_id,name')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Announcement $announcement): array => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'author' => $announcement->author->name,
                'published_at' => $announcement->published_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}

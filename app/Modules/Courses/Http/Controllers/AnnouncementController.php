<?php

namespace App\Modules\Courses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Courses\Actions\DeleteAnnouncement;
use App\Modules\Courses\Actions\PostAnnouncement;
use App\Modules\Courses\Actions\UpdateAnnouncement;
use App\Modules\Courses\Http\Requests\AnnouncementRequest;
use App\Modules\Courses\Models\Announcement;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * UC-008: the owning teacher posts, edits and deletes announcements of a
 * course space; students read them on the course space page.
 */
class AnnouncementController extends Controller
{
    public function store(AnnouncementRequest $request, CourseSpace $courseSpace, PostAnnouncement $post): RedirectResponse
    {
        Gate::authorize('manageAnnouncements', $courseSpace);

        /** @var User $user */
        $user = $request->user();
        $post->handle($user, $courseSpace, $request->announcement());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('courses.flash.announcement_posted')]);

        return to_route('courses.show', $courseSpace);
    }

    public function update(AnnouncementRequest $request, CourseSpace $courseSpace, Announcement $announcement, UpdateAnnouncement $update): RedirectResponse
    {
        Gate::authorize('update', $announcement);

        $update->handle($announcement, $request->announcement());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('courses.flash.announcement_updated')]);

        return to_route('courses.show', $courseSpace);
    }

    public function destroy(CourseSpace $courseSpace, Announcement $announcement, DeleteAnnouncement $delete): RedirectResponse
    {
        Gate::authorize('delete', $announcement);

        $delete->handle($announcement);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('courses.flash.announcement_deleted')]);

        return to_route('courses.show', $courseSpace);
    }
}

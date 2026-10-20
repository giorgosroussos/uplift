<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Actions\ListNotifications;
use App\Modules\Notifications\Actions\MarkAllNotificationsRead;
use App\Modules\Notifications\Actions\MarkNotification;
use App\Modules\Notifications\Actions\OpenNotification;
use App\Modules\Notifications\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-007: the notification centre of the signed-in user (DEC-002). Every
 * route acts on the user's own notifications only.
 */
class NotificationController extends Controller
{
    public function index(Request $request, ListNotifications $list): Response
    {
        return Inertia::render('Notifications/Index', [
            'entries' => $list->handle($this->user($request)),
        ]);
    }

    public function open(Request $request, Notification $notification, OpenNotification $open): RedirectResponse
    {
        Gate::authorize('update', $notification);

        $target = $open->handle($this->user($request), $notification);

        abort_if($target === null, 404);

        return redirect($target);
    }

    public function read(Notification $notification, MarkNotification $mark): RedirectResponse
    {
        Gate::authorize('update', $notification);

        $mark->handle($notification, read: true);

        return back();
    }

    public function unread(Notification $notification, MarkNotification $mark): RedirectResponse
    {
        Gate::authorize('update', $notification);

        $mark->handle($notification, read: false);

        return back();
    }

    public function readAll(Request $request, MarkAllNotificationsRead $markAll): RedirectResponse
    {
        $markAll->handle($this->user($request));

        return back();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

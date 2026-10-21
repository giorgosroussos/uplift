<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Actions\UpdateNotificationPreference;
use App\Modules\Notifications\Http\Requests\NotificationSettingsRequest;
use App\Modules\Notifications\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DEC-002: the signed-in user's switch for DEC-001 notification emails.
 * There is no way to address another user's preference.
 */
class NotificationSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Notifications/Settings', [
            'emailEnabled' => NotificationPreference::emailEnabledFor($user),
        ]);
    }

    public function update(NotificationSettingsRequest $request, UpdateNotificationPreference $update): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $update->handle($user, $request->boolean('email_enabled'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('notifications.settings.saved')]);

        return to_route('notifications.settings.edit');
    }
}

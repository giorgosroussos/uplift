<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\UpdateProfile;
use App\Modules\Identity\Http\Requests\UpdateProfileRequest;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\Avatars;
use App\Modules\Tenancy\Enums\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-006: the user's own profile (DATA-014). There is no route parameter: a
 * user only ever sees and edits their own record (OWN-001). Email, role,
 * date of birth and minor flag are shown read-only.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('view', $user);

        return Inertia::render('Identity/Settings/Profile', [
            'profile' => [
                'name' => $user->name,
                'nickname' => $user->nickname,
                'avatar' => $user->avatar,
                'locale' => $user->locale?->value,
                'email' => $user->email,
                'role' => $user->role->value,
                'date_of_birth' => $user->date_of_birth?->toDateString(),
                'is_minor' => $user->is_minor,
            ],
            'avatars' => Avatars::keys(),
            'locales' => array_map(fn (Locale $locale): string => $locale->value, Locale::cases()),
            'organizationLocale' => $user->organization->default_locale->value,
            // The language this response was rendered in (ApplyLocale).
            'currentLocale' => App::getLocale(),
        ]);
    }

    public function update(UpdateProfileRequest $request, UpdateProfile $updateProfile): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $updateProfile->handle($user, $request->profile());

        // The toast is rendered after the redirect, in the new language.
        App::setLocale($user->preferredLocale());
        Inertia::flash('toast', ['type' => 'success', 'message' => (string) __('profile.flash.saved')]);

        return to_route('settings.profile.edit');
    }
}

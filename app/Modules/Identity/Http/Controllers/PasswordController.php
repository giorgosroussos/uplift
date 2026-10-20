<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\UpdatePassword;
use App\Modules\Identity\Http\Requests\UpdatePasswordRequest;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-006 step 2: the user changes their own password (DATA-014, SEC-010).
 */
class PasswordController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('update', $user);

        return Inertia::render('Identity/Settings/Password', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(UpdatePasswordRequest $request, UpdatePassword $updatePassword): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $updatePassword->handle($user, $request->newPassword());

        Inertia::flash('toast', ['type' => 'success', 'message' => (string) __('profile.flash.password_changed')]);

        return to_route('settings.password.edit');
    }
}

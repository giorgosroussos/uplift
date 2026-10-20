<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Enums\Locale;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * UC-006 step 1: a user edits their own name, nickname, avatar and locale
 * (DATA-014). Nothing else of the user changes here.
 */
final class UpdateProfile
{
    /**
     * @param  array{name: string, nickname: string, avatar: string|null, locale: Locale|null}  $profile
     *
     * @throws ValidationException when another user of the organisation took the nickname meanwhile
     */
    public function handle(User $user, array $profile): void
    {
        $user->forceFill([
            'name' => $profile['name'],
            'nickname' => $profile['nickname'],
            'avatar' => $profile['avatar'],
            'locale' => $profile['locale'],
        ]);

        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            $user->refresh();

            throw ValidationException::withMessages([
                'nickname' => (string) __('profile.validation.nickname_taken'),
            ]);
        }
    }
}

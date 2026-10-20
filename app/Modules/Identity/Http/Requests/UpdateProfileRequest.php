<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Rules\Nickname;
use App\Modules\Identity\Support\Avatars;
use App\Modules\Tenancy\Enums\Locale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UC-006: the user's own profile (DATA-014). Only name, nickname, avatar and
 * locale are read; email, role, date_of_birth and is_minor never are.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('update', $user);
    }

    /**
     * @return array<string, array<int, ValidationRule|string|object>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['required', new Nickname($user->organization_id, (string) $user->getKey())],
            'avatar' => ['nullable', 'string', Rule::in(Avatars::keys())],
            'locale' => ['nullable', 'string', Rule::enum(Locale::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => (string) __('profile.attributes.name'),
            'nickname' => (string) __('profile.attributes.nickname'),
            'avatar' => (string) __('profile.attributes.avatar'),
            'locale' => (string) __('profile.attributes.locale'),
        ];
    }

    /**
     * @return array{name: string, nickname: string, avatar: string|null, locale: Locale|null}
     */
    public function profile(): array
    {
        $avatar = $this->validated('avatar');
        $locale = $this->validated('locale');

        return [
            'name' => (string) $this->validated('name'),
            'nickname' => (string) $this->validated('nickname'),
            'avatar' => is_string($avatar) && $avatar !== '' ? $avatar : null,
            'locale' => is_string($locale) && $locale !== '' ? Locale::from($locale) : null,
        ];
    }
}

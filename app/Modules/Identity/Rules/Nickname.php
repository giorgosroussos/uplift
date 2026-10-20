<?php

namespace App\Modules\Identity\Rules;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Support\OrganizationScope;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nicknames (DATA-014): 3-30 characters made of letters of any script,
 * digits, '_', '-' and '.', unique within the organisation ignoring case.
 * The value arrives trimmed (TrimStrings middleware).
 */
final class Nickname implements ValidationRule
{
    public const int MIN = 3;

    public const int MAX = 30;

    /**
     * Letters (with their combining marks), decimal digits, '_', '-' and '.'.
     */
    public const string PATTERN = '/^[\p{L}\p{M}\p{Nd}_.\-]+$/u';

    public function __construct(
        private readonly string $organizationId,
        private readonly ?string $ignoreUserId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail((string) __('profile.validation.nickname_format'));

            return;
        }

        $length = mb_strlen($value);

        if ($length < self::MIN || $length > self::MAX) {
            $fail((string) __('profile.validation.nickname_length', ['min' => self::MIN, 'max' => self::MAX]));

            return;
        }

        if ($this->taken($value)) {
            $fail((string) __('profile.validation.nickname_taken'));
        }
    }

    private function taken(string $nickname): bool
    {
        return User::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $this->organizationId)
            ->whereRaw('lower(nickname) = lower(?)', [$nickname])
            ->when($this->ignoreUserId !== null, fn ($query) => $query->whereKeyNot($this->ignoreUserId))
            ->exists();
    }
}

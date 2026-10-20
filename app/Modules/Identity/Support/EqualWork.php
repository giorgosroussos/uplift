<?php

namespace App\Modules\Identity\Support;

use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Str;

/**
 * Hashing work for the paths where no account (or no password) exists, so
 * login and password reset requests take as long for an unknown email as for
 * a known one and response time gives no hint whether an email has an
 * account (UC-003, UC-004).
 */
final class EqualWork
{
    /**
     * A hash of a random secret, made with the current hashing settings so
     * checking against it costs the same as checking a real password.
     */
    private static ?string $dummyHash = null;

    /**
     * The same cost as Hasher::check() against a stored password; always fails.
     */
    public static function check(Hasher $hasher, #[\SensitiveParameter] string $value): bool
    {
        self::$dummyHash ??= $hasher->make(Str::random(40));

        $hasher->check($value, self::$dummyHash);

        return false;
    }

    /**
     * The same cost as hashing a new reset token.
     */
    public static function make(Hasher $hasher): void
    {
        $hasher->make(Str::random(40));
    }
}

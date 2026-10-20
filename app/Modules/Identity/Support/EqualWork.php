<?php

namespace App\Modules\Identity\Support;

use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * Hashing work for the paths where no account, password or token exists, so
 * login and password reset requests take as long for an unknown email as for
 * a known one and response time gives no hint whether an email has an
 * account (UC-003, UC-004).
 */
final class EqualWork
{
    /**
     * Memo of the cached dummy hash for the current process.
     */
    private static ?string $dummyHash = null;

    /**
     * The same cost as Hasher::check() against a stored hash; always fails.
     *
     * The dummy hash is made once and kept in the cache across requests and
     * processes (keyed by the hashing settings, so a cost change makes a new
     * one): after warm-up this path pays one cache read and one check, like
     * a real password check.
     */
    public static function check(Hasher $hasher, #[\SensitiveParameter] string $value): bool
    {
        $hasher->check($value, self::dummyHash($hasher));

        return false;
    }

    /**
     * The same cost as hashing a new reset token.
     */
    public static function make(Hasher $hasher): void
    {
        $hasher->make(Str::random(40));
    }

    /**
     * Forgets the dummy hash (tests, or after changing hashing settings).
     * With $keepCache only this process forgets it, as at the start of a new
     * PHP-FPM request.
     */
    public static function flush(bool $keepCache = false): void
    {
        self::$dummyHash = null;

        if (! $keepCache) {
            Cache::forget(self::cacheKey());
        }
    }

    private static function dummyHash(Hasher $hasher): string
    {
        return self::$dummyHash ??= (string) Cache::rememberForever(
            self::cacheKey(),
            fn (): string => $hasher->make(Str::random(40)),
        );
    }

    private static function cacheKey(): string
    {
        $settings = [Config::get('hashing.driver'), Config::get('hashing.argon'), Config::get('hashing.bcrypt')];

        return 'identity:equal-work:'.md5((string) json_encode($settings));
    }
}

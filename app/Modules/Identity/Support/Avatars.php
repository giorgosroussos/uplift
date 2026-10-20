<?php

namespace App\Modules\Identity\Support;

/**
 * The preset avatars bundled with the app (DATA-014): keys avatar-01 to
 * avatar-12, served as public/avatars/<key>.svg. There are no uploads.
 */
final class Avatars
{
    public const int COUNT = 12;

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(
            fn (int $i): string => sprintf('avatar-%02d', $i),
            range(1, self::COUNT),
        );
    }
}

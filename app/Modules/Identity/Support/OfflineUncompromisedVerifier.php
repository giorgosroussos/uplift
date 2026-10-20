<?php

namespace App\Modules\Identity\Support;

use Illuminate\Contracts\Validation\UncompromisedVerifier;

/**
 * Password::uncompromised() outside production: no call to the live
 * Have I Been Pwned API (no live external systems outside production), but
 * a short local list of well-known breached passwords keeps the rule
 * observable in development and tests.
 */
final class OfflineUncompromisedVerifier implements UncompromisedVerifier
{
    /**
     * @var list<string>
     */
    public const array BREACHED = [
        'password',
        'password1',
        'password123',
        '12345678',
        '123456789',
        '1234567890',
        'qwertyuiop',
        'iloveyou',
        '11111111',
    ];

    /**
     * @param  array{value?: string, threshold?: int}  $data
     */
    public function verify($data): bool
    {
        return ! in_array(mb_strtolower((string) ($data['value'] ?? '')), self::BREACHED, true);
    }
}

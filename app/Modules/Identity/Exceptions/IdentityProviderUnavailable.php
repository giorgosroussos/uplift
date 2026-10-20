<?php

namespace App\Modules\Identity\Exceptions;

use RuntimeException;
use Throwable;

/**
 * An identity provider (EXT-001) failed or did not answer within the timeout.
 * The message never contains personal data.
 */
final class IdentityProviderUnavailable extends RuntimeException
{
    public static function because(string $provider, ?Throwable $previous = null): self
    {
        return new self("Identity provider [{$provider}] is unavailable.", previous: $previous);
    }
}

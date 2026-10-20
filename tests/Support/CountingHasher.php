<?php

namespace Tests\Support;

use Illuminate\Contracts\Hashing\Hasher;

/**
 * Wraps the application's hasher and counts make() and check() calls, so a
 * test can prove that work is equal across code paths without timing.
 *
 * @mixin Hasher
 */
final class CountingHasher implements Hasher
{
    public int $makes = 0;

    public int $checks = 0;

    public function __construct(private readonly Hasher $inner) {}

    public function info($hashedValue)
    {
        return $this->inner->info($hashedValue);
    }

    public function make(#[\SensitiveParameter] $value, array $options = [])
    {
        $this->makes++;

        return $this->inner->make($value, $options);
    }

    public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = [])
    {
        $this->checks++;

        return $this->inner->check($value, $hashedValue, $options);
    }

    public function needsRehash($hashedValue, array $options = [])
    {
        return $this->inner->needsRehash($hashedValue, $options);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->inner->{$method}(...$arguments);
    }
}

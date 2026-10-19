<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Models\Organization;

/**
 * The organisation (tenant) the current request or job acts in.
 *
 * Bound as a scoped instance, so it never leaks between requests or queued
 * jobs. Web requests get it from ResolveTenant (the host's subdomain);
 * console commands and jobs set it explicitly with run().
 */
final class TenantContext
{
    private ?Organization $organization = null;

    public function set(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function clear(): void
    {
        $this->organization = null;
    }

    public function current(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?string
    {
        return $this->organization?->getKey();
    }

    public function check(): bool
    {
        return $this->organization !== null;
    }

    /**
     * Runs the callback as the given tenant and restores the previous one.
     *
     * @template T
     *
     * @param  callable(Organization): T  $callback
     * @return T
     */
    public function run(Organization $organization, callable $callback): mixed
    {
        $previous = $this->organization;
        $this->organization = $organization;

        try {
            return $callback($organization);
        } finally {
            $this->organization = $previous;
        }
    }
}

<?php

namespace App\Modules\Identity\Rules;

use App\Modules\Identity\Support\SubdomainAvailability;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validation rule: the subdomain is well-formed, not reserved and not used
 * by another organisation; the message names the reason (UC-001).
 */
final class AvailableSubdomain implements ValidationRule
{
    public function __construct(private readonly SubdomainAvailability $availability) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $reason = is_string($value)
            ? $this->availability->unavailableReason($value)
            : SubdomainAvailability::INVALID;

        if ($reason !== null) {
            $fail(SubdomainAvailability::message($reason));
        }
    }
}

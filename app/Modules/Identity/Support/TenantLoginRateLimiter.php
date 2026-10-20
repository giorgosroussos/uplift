<?php

namespace App\Modules\Identity\Support;

use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

/**
 * Login throttling: 5 failed attempts per minute per email (SEC-010),
 * whatever the client IP. An email identifies an account only inside its
 * organisation, so the key also carries the organisation.
 */
class TenantLoginRateLimiter extends LoginRateLimiter
{
    protected function throttleKey(Request $request)
    {
        $email = Str::lower((string) $request->input(Fortify::username()));

        return 'login|'.(app(TenantContext::class)->id() ?? '-').'|'.Str::transliterate($email);
    }
}

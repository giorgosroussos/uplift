<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Responses\ProblemDetails;
use App\Modules\Identity\Support\SubdomainAvailability;
use App\Modules\Tenancy\Support\Subdomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * UC-001 step 2: live availability check of a subdomain on the sign-up
 * page. An available subdomain answers 200; an invalid, reserved or taken
 * one answers 422 as RFC 9457 problem details naming the reason.
 * Rate-limited to 60 requests per minute per IP (SEC-010).
 */
class SubdomainCheckController extends Controller
{
    public const string PROBLEM_TYPE = 'urn:uplift:problem:subdomain-unavailable';

    public function __invoke(Request $request, SubdomainAvailability $availability): JsonResponse
    {
        $subdomain = $request->query('subdomain');
        $subdomain = is_string($subdomain) ? $subdomain : '';

        $reason = $availability->unavailableReason($subdomain);

        if ($reason === null) {
            return new JsonResponse([
                'subdomain' => Subdomain::normalize($subdomain),
                'available' => true,
            ]);
        }

        $message = SubdomainAvailability::message($reason);

        return ProblemDetails::response(
            422,
            self::PROBLEM_TYPE,
            (string) __('registration.subdomain.unavailable'),
            $message,
            [
                'subdomain' => Subdomain::normalize($subdomain),
                'available' => false,
                'reason' => $reason,
                'errors' => ['subdomain' => [$message]],
            ],
        );
    }
}

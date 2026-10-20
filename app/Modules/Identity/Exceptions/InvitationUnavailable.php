<?php

namespace App\Modules\Identity\Exceptions;

use App\Modules\Identity\Enums\InvitationStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * UC-005 exception: the invitation was accepted, revoked or has expired. The
 * invitee sees why, with a message to contact the organisation's admin.
 */
final class InvitationUnavailable extends RuntimeException
{
    public function __construct(public readonly InvitationStatus $status)
    {
        parent::__construct("The invitation is [{$status->value}].");
    }

    public function render(Request $request): Response
    {
        return Inertia::render('auth/InvitationUnavailable', [
            'reason' => $this->status->value,
        ])->toResponse($request)->setStatusCode(Response::HTTP_GONE);
    }
}

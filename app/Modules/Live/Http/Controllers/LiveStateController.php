<?php

namespace App\Modules\Live\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Live\Actions\ShowLiveHostState;
use App\Modules\Live\Actions\ShowLiveParticipantState;
use App\Modules\Live\Models\LiveParticipant;
use App\Modules\Live\Models\LiveSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * State snapshot endpoints of a live quiz (FLOW-002, ARCH-006): JSON with
 * RFC 9457 errors, fetched on load, after a reconnection and every 2
 * seconds while the websocket is down. Route model binding only finds
 * records of the current tenant; LiveSessionPolicy::host and
 * LiveParticipantPolicy::play (OWN-012, OWN-013) decide who may.
 */
class LiveStateController extends Controller
{
    public function host(LiveSession $liveSession, ShowLiveHostState $state): JsonResponse
    {
        Gate::authorize('host', $liveSession);

        return new JsonResponse($state->handle($liveSession));
    }

    public function participant(LiveParticipant $liveParticipant, ShowLiveParticipantState $state): JsonResponse
    {
        Gate::authorize('play', $liveParticipant);

        return new JsonResponse($state->handle($liveParticipant));
    }
}

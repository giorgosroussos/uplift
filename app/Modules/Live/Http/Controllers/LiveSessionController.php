<?php

namespace App\Modules\Live\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Activities\Models\Activity;
use App\Modules\Identity\Models\User;
use App\Modules\Live\Actions\CancelLiveLobby;
use App\Modules\Live\Actions\OpenLiveLobby;
use App\Modules\Live\Actions\StartLiveSession;
use App\Modules\Live\Models\LiveSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * UC-024: the host teacher opens the lobby of a live quiz, starts it
 * (manual start mode) or closes it without starting. Route model binding
 * only finds records of the current tenant; LiveSessionPolicy decides who
 * may. Refusals come back as validation errors on "session".
 */
class LiveSessionController extends Controller
{
    public function open(Request $request, Activity $activity, OpenLiveLobby $open): RedirectResponse
    {
        Gate::authorize('open', [LiveSession::class, $activity]);

        /** @var User $user */
        $user = $request->user();
        $open->handle($user, $activity);

        return back();
    }

    public function start(LiveSession $liveSession, StartLiveSession $start): RedirectResponse
    {
        Gate::authorize('host', $liveSession);

        $start->handle($liveSession);

        return back();
    }

    public function cancel(LiveSession $liveSession, CancelLiveLobby $cancel): RedirectResponse
    {
        Gate::authorize('host', $liveSession);

        $cancel->handle($liveSession);

        return back();
    }
}

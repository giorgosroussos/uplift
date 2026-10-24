<?php

use App\Modules\Live\Broadcasting\LiveChannels;
use App\Modules\Live\Broadcasting\LiveHostChannel;
use App\Modules\Live\Broadcasting\LiveSessionChannel;
use Illuminate\Support\Facades\Broadcast;

/*
| Live quiz channels (SEC-010): presence and private only, the organisation
| id in the name, authorised by the same Policies as the HTTP routes.
| Answers never travel over these channels (ARCH-006).
*/

Broadcast::channel(LiveChannels::SESSION, LiveSessionChannel::class);
Broadcast::channel(LiveChannels::HOST, LiveHostChannel::class);

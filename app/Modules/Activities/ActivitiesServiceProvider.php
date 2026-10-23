<?php

namespace App\Modules\Activities;

use App\Modules\Activities\Http\Responses\ProblemDetails;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Booking;
use App\Modules\Activities\Models\Poll;
use App\Modules\Activities\Models\PollParticipation;
use App\Modules\Activities\Models\SlotActivity;
use App\Modules\Activities\Models\TopicActivity;
use App\Modules\Activities\Models\TopicChoice;
use App\Modules\Activities\Models\TopicProposal;
use App\Modules\Activities\Policies\ActivityPolicy;
use App\Modules\Activities\Policies\BookingPolicy;
use App\Modules\Activities\Policies\PollParticipationPolicy;
use App\Modules\Activities\Policies\PollPolicy;
use App\Modules\Activities\Policies\SlotActivityPolicy;
use App\Modules\Activities\Policies\TopicActivityPolicy;
use App\Modules\Activities\Policies\TopicChoicePolicy;
use App\Modules\Activities\Policies\TopicProposalPolicy;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Activities\Support\PollType;
use App\Modules\Activities\Support\SlotBookingType;
use App\Modules\Activities\Support\TopicProposalType;
use App\Modules\Activities\Support\TopicSelectionType;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Activity base and lifecycle, the creation wizard and the submissions
 * overview (UC-022, UC-028), polls (UC-013), topic selection (UC-014) and
 * topic proposals with their approval (UC-015, UC-027) and slot booking
 * (UC-016). Activity types plug in through the ActivityType contract and
 * the ActivityTypes registry (ARCH-004); the poll, topic selection, topic
 * proposal and slot booking types register themselves like any other.
 */
class ActivitiesServiceProvider extends ModuleServiceProvider
{
    /**
     * Routes whose errors are RFC 9457 problem details (wizard autosave).
     */
    public const array PROBLEM_ROUTES = ['activities.store', 'activities.wizard.update'];

    public function register(): void
    {
        $this->app->singleton(ActivityTypes::class);

        // ARCH-004: the poll, the topic selection, the topic proposal and the slot booking plug into the activity framework.
        $this->callAfterResolving(ActivityTypes::class, function (ActivityTypes $types): void {
            $types->register(PollType::class);
            $types->register(TopicSelectionType::class);
            $types->register(TopicProposalType::class);
            $types->register(SlotBookingType::class);
        });
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(Poll::class, PollPolicy::class);
        Gate::policy(PollParticipation::class, PollParticipationPolicy::class);
        Gate::policy(TopicActivity::class, TopicActivityPolicy::class);
        Gate::policy(TopicChoice::class, TopicChoicePolicy::class);
        Gate::policy(TopicProposal::class, TopicProposalPolicy::class);
        Gate::policy(SlotActivity::class, SlotActivityPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);

        // The exception handler is resolved lazily, when the first exception is rendered.
        $this->app->afterResolving(Handler::class, function (Handler $handler): void {
            $handler->renderable(function (Throwable $e, Request $request): ?JsonResponse {
                if (! $request->routeIs(...self::PROBLEM_ROUTES)) {
                    return null;
                }

                return ProblemDetails::for($e);
            });
        });
    }
}

<?php

namespace App\Modules\Live;

use App\Modules\Activities\Http\Responses\ProblemDetails;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Live\Console\AdvanceLiveSessionsCommand;
use App\Modules\Live\Exceptions\LiveSessionClosed;
use App\Modules\Live\Models\LiveAnswer;
use App\Modules\Live\Models\LiveParticipant;
use App\Modules\Live\Models\LiveSession;
use App\Modules\Live\Policies\LiveAnswerPolicy;
use App\Modules\Live\Policies\LiveParticipantPolicy;
use App\Modules\Live\Policies\LiveSessionPolicy;
use App\Modules\Live\Support\LiveQuizType;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Live quizzes (UC-024, UC-025): the live session engine — lobby, manual
 * or scheduled start, one common end time, answers, early and timed
 * finish, scoring and final ranks (SCOPE-021, SCOPE-022).
 */
class LiveServiceProvider extends ModuleServiceProvider
{
    /**
     * JSON routes whose errors are RFC 9457 problem details.
     */
    public const array PROBLEM_ROUTES = ['live.answers.update'];

    public const string PROBLEM_CLOSED = 'urn:uplift:problem:live-closed';

    public function register(): void
    {
        // SCOPE-029: a started live session counts as a submission of the
        // quiz. Registered after Assessment (provider order), so it
        // replaces QuizType, which it wraps.
        $this->callAfterResolving(ActivityTypes::class, function (ActivityTypes $types): void {
            $types->register(LiveQuizType::class);
        });
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(LiveSession::class, LiveSessionPolicy::class);
        Gate::policy(LiveParticipant::class, LiveParticipantPolicy::class);
        Gate::policy(LiveAnswer::class, LiveAnswerPolicy::class);

        // SCOPE-021: scheduled quizzes start and sessions past their end
        // time finish within seconds, without teacher action.
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('live:advance')->everyFiveSeconds()->withoutOverlapping();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([AdvanceLiveSessionsCommand::class]);
        }

        $this->app->afterResolving(Handler::class, function (Handler $handler): void {
            $handler->renderable(function (Throwable $e, Request $request): ?JsonResponse {
                if (! $request->routeIs(...self::PROBLEM_ROUTES)) {
                    return null;
                }

                if ($e instanceof LiveSessionClosed) {
                    return ProblemDetails::response(
                        409,
                        self::PROBLEM_CLOSED,
                        (string) __('live.problems.closed'),
                        (string) __('live.errors.closed'),
                    );
                }

                return ProblemDetails::for($e);
            });
        });
    }
}

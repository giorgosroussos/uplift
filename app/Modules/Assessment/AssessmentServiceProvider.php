<?php

namespace App\Modules\Assessment;

use App\Modules\Activities\Events\ActivityClosed;
use App\Modules\Activities\Http\Responses\ProblemDetails;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Assessment\Console\SubmitDueAttemptsCommand;
use App\Modules\Assessment\Exceptions\AttemptClosed;
use App\Modules\Assessment\Listeners\SubmitAttemptsOfClosedQuiz;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Policies\AnswerPolicy;
use App\Modules\Assessment\Policies\QuestionPolicy;
use App\Modules\Assessment\Policies\QuizAttemptPolicy;
use App\Modules\Assessment\Support\QuizType;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Questions, the personal question bank (UC-023) and quizzes: the quiz
 * activity type (UC-022), attempts and answers (UC-012).
 */
class AssessmentServiceProvider extends ModuleServiceProvider
{
    /**
     * JSON routes whose errors are RFC 9457 problem details.
     */
    public const array PROBLEM_ROUTES = ['assessment.questions.copyable', 'assessment.attempts.answers.update'];

    public const string PROBLEM_ATTEMPT_CLOSED = 'urn:uplift:problem:attempt-closed';

    protected array $listen = [
        // SCOPE-024: closing a quiz submits its in-progress attempts.
        ActivityClosed::class => [SubmitAttemptsOfClosedQuiz::class],
    ];

    public function register(): void
    {
        // ARCH-004: the quiz plugs into the activity framework.
        $this->callAfterResolving(ActivityTypes::class, function (ActivityTypes $types): void {
            $types->register(QuizType::class);
        });
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Question::class, QuestionPolicy::class);
        Gate::policy(QuizAttempt::class, QuizAttemptPolicy::class);
        Gate::policy(Answer::class, AnswerPolicy::class);

        // SCOPE-024: attempts whose time is up are submitted within a minute.
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('assessment:submit-due-attempts')->everyMinute()->withoutOverlapping();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SubmitDueAttemptsCommand::class]);
        }

        $this->app->afterResolving(Handler::class, function (Handler $handler): void {
            $handler->renderable(function (Throwable $e, Request $request): ?JsonResponse {
                if (! $request->routeIs(...self::PROBLEM_ROUTES)) {
                    return null;
                }

                if ($e instanceof AttemptClosed) {
                    return ProblemDetails::response(
                        409,
                        self::PROBLEM_ATTEMPT_CLOSED,
                        (string) __('assessment.attempt.problems.closed'),
                        (string) __('assessment.attempt.errors.closed'),
                    );
                }

                return ProblemDetails::for($e);
            });
        });
    }
}

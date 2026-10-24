<?php

namespace App\Modules\Live\Actions;

use App\Modules\Activities\Enums\ActivityStatus;
use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\LiveStartMode;
use App\Modules\Assessment\Enums\QuizMode;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Live\Enums\LiveSessionStatus;
use App\Modules\Live\Models\LiveSession;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\OrganizationScope;
use App\Modules\Tenancy\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-024 step 1: starts every published scheduled live quiz whose start
 * time has come and that was not run yet, without teacher action, in
 * every organisation. The lobby students joined is started; without one,
 * a lobby hosted by the course space's teacher is opened and started at
 * once. Run by the scheduler; each quiz starts as its own tenant.
 */
class StartScheduledLiveSessions
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly StartLiveSession $start,
    ) {}

    /**
     * @return int the number of sessions started
     */
    public function handle(): int
    {
        $due = Quiz::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('mode', QuizMode::Live->value)
            ->where('live_start_mode', LiveStartMode::Scheduled->value)
            ->where('live_scheduled_start_at', '<=', CarbonImmutable::now())
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from((new Activity)->getTable())
                ->whereColumn('activities.id', 'quizzes.activity_id')
                ->where('activities.status', ActivityStatus::Published->value)
                ->whereNull('activities.deleted_at'))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from((new LiveSession)->getTable())
                ->whereColumn('live_sessions.quiz_id', 'quizzes.activity_id')
                ->whereColumn('live_sessions.organization_id', 'quizzes.organization_id')
                ->whereIn('live_sessions.status', LiveSessionStatus::started()))
            ->get(['activity_id', 'organization_id'])
            ->groupBy('organization_id');

        $count = 0;

        foreach ($due as $organizationId => $quizzes) {
            $organization = Organization::query()->find($organizationId);

            if ($organization === null) {
                continue;
            }

            $count += $this->tenant->run($organization, function () use ($quizzes): int {
                $started = 0;

                foreach ($quizzes as $quiz) {
                    if ($this->startQuiz($quiz->activity_id)) {
                        $started++;
                    }
                }

                return $started;
            });
        }

        return $count;
    }

    private function startQuiz(string $quizId): bool
    {
        $activity = Activity::query()->find($quizId);

        if ($activity === null) {
            return false;
        }

        try {
            $session = $this->lobby($activity);
            $this->start->begin($session, LiveStartMode::Scheduled);
        } catch (ValidationException) {
            // Started meanwhile, or no longer runnable: nothing to do.
            return false;
        }

        return true;
    }

    /**
     * @throws ValidationException
     */
    private function lobby(Activity $activity): LiveSession
    {
        return DB::transaction(function () use ($activity): LiveSession {
            Quiz::query()->lockForUpdate()->find($activity->id);

            $current = OpenLiveLobby::current($activity->id);

            if ($current !== null) {
                return $current;
            }

            $session = new LiveSession;
            $session->quiz_id = $activity->id;
            $session->host_id = $activity->courseSpace->teacher_id;
            $session->save();

            return $session;
        });
    }
}

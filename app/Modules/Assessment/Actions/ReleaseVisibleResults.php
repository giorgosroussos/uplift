<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\ResultVisibility;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\OrganizationScope;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * DEC-003: releases every graded, unreleased attempt whose score has become
 * visible since it was graded (the deadline passed, or the teacher changed
 * the visibility or the deadline), in every organisation. Run by the
 * scheduler every minute; each attempt is released as its own tenant and
 * re-checked under a row lock (ReleaseResult).
 */
class ReleaseVisibleResults
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly ReleaseResult $release,
    ) {}

    /**
     * @return int the number of attempts released
     */
    public function handle(): int
    {
        $candidates = QuizAttempt::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('status', AttemptStatus::Graded->value)
            ->whereNull('result_released_at')
            ->whereHas('quiz', function (Builder $quiz): void {
                /** @var Builder<Quiz> $quiz */
                $quiz->withoutGlobalScope(OrganizationScope::class)
                    ->where('score_visibility', '!=', ResultVisibility::Hidden->value);
            })
            ->get(['id', 'organization_id'])
            ->groupBy('organization_id');

        $count = 0;

        foreach ($candidates as $organizationId => $attempts) {
            $organization = Organization::query()->find($organizationId);

            if ($organization === null) {
                continue;
            }

            $count += $this->tenant->run($organization, function () use ($attempts): int {
                $released = 0;

                foreach ($attempts as $attempt) {
                    $released += DB::transaction(function () use ($attempt): int {
                        $locked = QuizAttempt::query()->lockForUpdate()->find($attempt->id);

                        return $locked !== null && $this->release->handle($locked) ? 1 : 0;
                    });
                }

                return $released;
            });
        }

        return $count;
    }
}

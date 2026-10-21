<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\OrganizationScope;
use App\Modules\Tenancy\Support\TenantContext;
use Carbon\CarbonImmutable;

/**
 * SCOPE-024: submits every in-progress attempt whose ends_at has passed
 * (server time), in every organisation, with the answers saved so far.
 * Run by the scheduler every minute; each attempt is submitted as its
 * own tenant.
 */
class SubmitDueAttempts
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly SubmitQuizAttempt $submit,
    ) {}

    /**
     * @return int the number of attempts submitted
     */
    public function handle(): int
    {
        $due = QuizAttempt::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('status', AttemptStatus::InProgress->value)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', CarbonImmutable::now())
            ->get(['id', 'organization_id'])
            ->groupBy('organization_id');

        $count = 0;

        foreach ($due as $organizationId => $attempts) {
            $organization = Organization::query()->find($organizationId);

            if ($organization === null) {
                continue;
            }

            $count += $this->tenant->run($organization, function () use ($attempts): int {
                $submitted = 0;

                foreach ($attempts as $attempt) {
                    $fresh = QuizAttempt::query()->find($attempt->id);

                    if ($fresh !== null && $this->submit->submitIfDue($fresh)) {
                        $submitted++;
                    }
                }

                return $submitted;
            });
        }

        return $count;
    }
}

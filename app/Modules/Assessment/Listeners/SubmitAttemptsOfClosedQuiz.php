<?php

namespace App\Modules\Assessment\Listeners;

use App\Modules\Activities\Events\ActivityClosed;
use App\Modules\Assessment\Actions\SubmitQuizAttempt;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\TenantContext;

/**
 * UC-012 / SCOPE-024: when the teacher closes a quiz, every in-progress
 * attempt is submitted with the answers saved so far. Runs synchronously
 * after the close has committed; other activity types have no attempts.
 */
class SubmitAttemptsOfClosedQuiz
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly SubmitQuizAttempt $submit,
    ) {}

    public function handle(ActivityClosed $event): void
    {
        $organization = Organization::query()->find($event->organizationId);

        if ($organization === null) {
            return;
        }

        $this->tenant->run($organization, function () use ($event): void {
            $attempts = QuizAttempt::query()
                ->where('quiz_id', $event->activityId)
                ->where('status', AttemptStatus::InProgress->value)
                ->get();

            foreach ($attempts as $attempt) {
                $this->submit->handle($attempt);
            }
        });
    }
}

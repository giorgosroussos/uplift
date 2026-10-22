<?php

namespace App\Modules\Assessment\Actions;

use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Events\ResultReleased;
use App\Modules\Assessment\Models\QuizAttempt;
use Carbon\CarbonImmutable;

/**
 * DEC-003: releases an attempt's result once it is graded (final) and its
 * score is visible to the student (score_visibility immediate, or
 * after_deadline with no deadline or a passed one; never hidden). Sets
 * result_released_at and dispatches ResultReleased, both at most once per
 * attempt; a release is never revoked.
 *
 * The caller passes the attempt locked for update inside its transaction,
 * so the finalising action and the release command never release twice.
 */
class ReleaseResult
{
    /**
     * @return bool whether the attempt was released now
     */
    public function handle(QuizAttempt $locked): bool
    {
        if ($locked->status !== AttemptStatus::Graded || $locked->result_released_at !== null) {
            return false;
        }

        $now = CarbonImmutable::now();

        if (! $locked->quiz->isScoreVisible($locked, $now)) {
            return false;
        }

        $locked->result_released_at = $now;
        $locked->save();

        ResultReleased::dispatch($locked->organization_id, $locked->id);

        return true;
    }
}

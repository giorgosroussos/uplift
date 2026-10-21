<?php

namespace App\Modules\Assessment\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ENT-AnswerMatch: a left item matched to a right item in an answer.
 * Access follows its answer (OWN-007).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $answer_id
 * @property string $left_pair_id
 * @property string $right_pair_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Answer $answer
 */
class AnswerMatch extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    /**
     * @return BelongsTo<Answer, $this>
     */
    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }

    /**
     * @return BelongsTo<MatchingPair, $this>
     */
    public function leftPair(): BelongsTo
    {
        return $this->belongsTo(MatchingPair::class, 'left_pair_id');
    }

    /**
     * @return BelongsTo<MatchingPair, $this>
     */
    public function rightPair(): BelongsTo
    {
        return $this->belongsTo(MatchingPair::class, 'right_pair_id');
    }
}

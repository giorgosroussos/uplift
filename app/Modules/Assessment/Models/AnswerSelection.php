<?php

namespace App\Modules\Assessment\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ENT-AnswerSelection: a choice option selected in an answer. Access
 * follows its answer (OWN-007).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $answer_id
 * @property string $choice_option_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Answer $answer
 * @property-read ChoiceOption $choiceOption
 */
class AnswerSelection extends Model
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
     * @return BelongsTo<ChoiceOption, $this>
     */
    public function choiceOption(): BelongsTo
    {
        return $this->belongsTo(ChoiceOption::class);
    }
}

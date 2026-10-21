<?php

namespace App\Modules\Assessment\Models;

use App\Modules\Assessment\Database\Factories\ChoiceOptionFactory;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ENT-ChoiceOption: an option of a single-choice, multiple-choice or
 * true/false question, ordered by position. True/false options store the
 * fixed texts 'true' and 'false' (DATA-016). Access follows its question.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $question_id
 * @property string $text
 * @property bool $is_correct
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Question $question
 */
#[UseFactory(ChoiceOptionFactory::class)]
class ChoiceOption extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<ChoiceOptionFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'text',
        'is_correct',
        'position',
    ];

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'position' => 'integer',
        ];
    }
}

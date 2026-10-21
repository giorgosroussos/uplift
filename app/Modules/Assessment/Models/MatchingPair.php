<?php

namespace App\Modules\Assessment\Models;

use App\Modules\Assessment\Database\Factories\MatchingPairFactory;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ENT-MatchingPair: a correct left-right pair of a matching question,
 * ordered by position. Access follows its question.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $question_id
 * @property string $left_text
 * @property string $right_text
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Question $question
 */
#[UseFactory(MatchingPairFactory::class)]
class MatchingPair extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<MatchingPairFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'left_text',
        'right_text',
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
            'position' => 'integer',
        ];
    }
}

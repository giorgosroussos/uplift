<?php

namespace App\Modules\Assessment\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ENT-QuizQuestion: the place of a question in a quiz. The question is the
 * quiz's own copy (in_bank = false), so editing or deleting a bank question
 * never changes a quiz (DATA-008, DATA-016).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $quiz_id
 * @property string $question_id
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Quiz $quiz
 * @property-read Question $question
 */
class QuizQuestion extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'position',
    ];

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

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

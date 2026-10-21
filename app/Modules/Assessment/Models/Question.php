<?php

namespace App\Modules\Assessment\Models;

use App\Modules\Assessment\Database\Factories\QuestionFactory;
use App\Modules\Assessment\Enums\QuestionType;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ENT-Question: a question of one of five types (UC-023). With in_bank it
 * belongs to its owner's personal bank (OWN-016, Policies/QuestionPolicy);
 * a question placed in a quiz is a separate copy with in_bank false, so
 * editing or deleting the bank question never touches it (DATA-008,
 * DATA-016). Hard-deleted; options and pairs go with it.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $owner_id
 * @property QuestionType $type
 * @property string $prompt
 * @property string $points decimal with 2 places, e.g. "1.50"
 * @property bool $in_bank
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $owner
 * @property-read Collection<int, ChoiceOption> $options
 * @property-read Collection<int, MatchingPair> $pairs
 */
#[UseFactory(QuestionFactory::class)]
class Question extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'prompt',
        'points',
    ];

    public function isBankQuestionOf(User $user): bool
    {
        return $this->in_bank && $this->owner_id === $user->getKey();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<ChoiceOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ChoiceOption::class)->orderBy('position');
    }

    /**
     * @return HasMany<MatchingPair, $this>
     */
    public function pairs(): HasMany
    {
        return $this->hasMany(MatchingPair::class)->orderBy('position');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'points' => 'decimal:2',
            'in_bank' => 'boolean',
        ];
    }
}

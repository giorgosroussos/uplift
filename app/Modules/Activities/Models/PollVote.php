<?php

namespace App\Modules\Activities\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * ENT-PollVote: one chosen option. It carries no user reference
 * (DATA-006), no timestamps and a random UUID (v4, not time-ordered), so it
 * can never be matched to a participation, not even by the order or time
 * of writing.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $poll_option_id
 * @property-read PollOption $option
 */
class PollVote extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    public $timestamps = false;

    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * @return BelongsTo<PollOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(PollOption::class, 'poll_option_id');
    }
}

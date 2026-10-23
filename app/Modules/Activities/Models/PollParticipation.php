<?php

namespace App\Modules\Activities\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ENT-PollParticipation: THAT a student voted in a poll, never what
 * (DATA-006); one per student and poll, so a second vote is refused.
 * OWN-011 (PollParticipationPolicy): only the student sees their own
 * participation; teachers and admins see only the number of participants.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $poll_id
 * @property string $student_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Poll $poll
 * @property-read User $student
 */
class PollParticipation extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    /**
     * @return BelongsTo<Poll, $this>
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class, 'poll_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}

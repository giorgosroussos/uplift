<?php

namespace App\Modules\Activities\Models;

use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ENT-Booking: the current booking of a student (individual activity) or
 * of a group (group activity, SCOPE-012); one per holder and activity
 * (SCOPE-028). A change moves the row to the new slot, a cancellation
 * deletes it. Written only by BookSlot and CancelBooking. OWN-010
 * (BookingPolicy): a student changes only their own or their group's
 * booking; a teacher sees bookings only of their own activities.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $slot_id
 * @property string|null $student_id
 * @property string|null $group_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Slot $slot
 * @property-read User|null $student
 * @property-read Group|null $group
 */
class Booking extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    /**
     * @return BelongsTo<Slot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class, 'slot_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * The group, also once it is deleted: its bookings stay (SCOPE-025).
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id')->withTrashed();
    }
}

<?php

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Booking;
use App\Modules\Activities\Models\Slot;
use App\Modules\Activities\Models\SlotActivity;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-016 (P4-T5): slot booking. The teacher offers time slots with a
| capacity in the activity wizard (SCOPE-028); an enrolled student, or in a
| group activity one member for the group (SCOPE-012, SCOPE-025), books a
| slot with free places that has not started, and may move or cancel the
| booking until the deadline while its slot has not started. OWN-010: a
| student changes only their own or their group's booking; a teacher sees
| bookings only of their own activities. The concurrent booking criterion
| is in SlotBookingConcurrencyTest.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create(['name' => 'Alice Student']);
    $this->classmate = User::factory()->student()->create(['name' => 'Bob Classmate']);
    $this->third = User::factory()->student()->create(['name' => 'Carol Third']);
    $this->outsider = User::factory()->student()->create(['name' => 'Olga Outsider']);
    $this->admin = User::factory()->orgAdmin()->create();

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();

    foreach ([$this->student, $this->classmate, $this->third] as $enrolled) {
        Enrolment::factory()->for($this->space, 'courseSpace')->for($enrolled, 'student')->create();
    }
});

function sbUrl(string $path): string
{
    return test()->tenantUrl(test()->acme, ltrim($path, '/'));
}

/**
 * A slot booking of the course space; each slot key is its start (30
 * minutes long), each value its capacity.
 *
 * @param  array<string, int>  $slots
 * @param  array<string, mixed>  $activity  activity attributes
 */
function sbActivity(array $slots = ['2026-11-03 10:00' => 1, '2026-11-03 11:00' => 2], string $state = 'published', array $activity = []): SlotActivity
{
    $factory = Activity::factory()->ofKind(ActivityKind::SlotBooking)->for(test()->space, 'courseSpace');
    $factory = match ($state) {
        'published' => $factory->published(),
        'closed' => $factory->closed(),
        default => $factory,
    };

    return SlotActivity::factory()->for($factory->create($activity), 'activity')->withSlots($slots)->create();
}

function sbGroupActivity(int $groupSize = 3, array $slots = ['2026-11-03 10:00' => 1, '2026-11-03 11:00' => 2]): SlotActivity
{
    return sbActivity($slots, 'published', ['is_group' => true, 'group_size' => $groupSize]);
}

function sbSlot(SlotActivity $slotActivity, string $start): Slot
{
    return Slot::query()->where('slot_activity_id', $slotActivity->activity_id)
        ->where('starts_at', CarbonImmutable::parse($start)->utc())
        ->firstOrFail();
}

/**
 * A group of the course space whose first member is its owner; every
 * member is a current member.
 *
 * @param  list<User>  $members
 */
function sbGroup(string $name, array $members, ?CourseSpace $space = null): Group
{
    $owner = $members[0];
    $group = Group::factory()->for($space ?? test()->space, 'courseSpace')->ownedBy($owner)->create(['name' => $name]);
    GroupMembership::factory()->for($group, 'group')->for($owner, 'student')->create(['status' => GroupMembershipStatus::Member]);

    foreach (array_slice($members, 1) as $member) {
        GroupMembership::factory()->for($group, 'group')->for($member, 'student')->create()
            ->transitionTo(GroupMembershipStatus::Member);
    }

    return $group;
}

/**
 * @param  array<string, mixed>  $extra
 */
function sbBook(User $user, SlotActivity $slotActivity, Slot|string $slot, array $extra = []): TestResponse
{
    return test()->actingAs($user)->from(sbUrl("slots/{$slotActivity->activity_id}"))
        ->put(sbUrl("slots/{$slotActivity->activity_id}/booking"), [
            'slot_id' => $slot instanceof Slot ? $slot->id : $slot,
            ...$extra,
        ]);
}

/**
 * @param  array<string, mixed>  $extra
 */
function sbCancel(User $user, SlotActivity $slotActivity, array $extra = []): TestResponse
{
    return test()->actingAs($user)->from(sbUrl("slots/{$slotActivity->activity_id}"))
        ->delete(sbUrl("slots/{$slotActivity->activity_id}/booking"), $extra);
}

/**
 * A booking stored directly (as BookSlot stores it), for setting up.
 */
function sbHold(Slot $slot, ?User $student = null, ?Group $group = null): Booking
{
    $booking = new Booking;
    $booking->slot_id = $slot->id;
    $booking->student_id = $student?->getKey();
    $booking->group_id = $group?->id;
    $booking->save();

    return $booking;
}

/**
 * @return array<string, list<string>> slot start (H:i) => holder ids (student or group)
 */
function sbHolders(SlotActivity $slotActivity): array
{
    $holders = [];

    foreach ($slotActivity->slots()->get() as $slot) {
        $holders[$slot->starts_at->utc()->format('H:i')] = $slot->bookings()->get()
            ->map(fn (Booking $booking): string => (string) ($booking->student_id ?? $booking->group_id))
            ->sort()->values()->all();
    }

    return $holders;
}

/**
 * @return array<string, int> slot start (H:i) => remaining places, as the page shows them
 */
function sbRemaining(User $viewer, SlotActivity $slotActivity): array
{
    $props = test()->actingAs($viewer)->get(sbUrl("slots/{$slotActivity->activity_id}"))
        ->assertOk()
        ->viewData('page')['props'];

    return collect($props['slotBooking']['slots'])
        ->mapWithKeys(fn (array $slot): array => [CarbonImmutable::parse($slot['starts_at'])->utc()->format('H:i') => $slot['remaining']])
        ->all();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sbSlotInput(array $overrides = []): array
{
    return [
        'id' => (string) Str::uuid(),
        'starts_at' => '2026-11-03T10:00:00Z',
        'ends_at' => '2026-11-03T10:30:00Z',
        'location' => null,
        'capacity' => 1,
        ...$overrides,
    ];
}

describe('building a slot booking in the wizard', function () {
    beforeEach(function () {
        $response = $this->actingAs($this->teacher)->postJson(sbUrl('activities'), [
            'course_space_id' => $this->space->id,
            'type' => 'slot_booking',
            'title' => 'Presentation slots',
            'description' => null,
            'deadline_at' => null,
        ])->assertCreated();

        $this->activity = Activity::query()->findOrFail($response->json('id'));
        $this->contentUrl = sbUrl("activities/{$this->activity->id}/wizard/content");
    });

    it('offers the slot booking type with a slots step', function () {
        expect(app(ActivityTypes::class)->has(ActivityKind::SlotBooking))->toBeTrue();

        $this->actingAs($this->teacher)->get(sbUrl("activities/{$this->activity->id}/edit"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('types', fn ($types) => collect($types)->contains('slot_booking'))
                ->where('steps.1.key', 'content')
                ->where('steps.1.component', 'Activities/SlotBooking/Slots')
                ->where('steps.1.complete', false)
                ->where('steps.2.key', 'review'));
    });

    it('stores the slots in UTC with the slot duration and restores them', function () {
        $ids = [(string) Str::uuid(), (string) Str::uuid()];

        $this->actingAs($this->teacher)->putJson($this->contentUrl, [
            'slot_duration' => 1800,
            'slots' => [
                sbSlotInput(['id' => $ids[0], 'starts_at' => '2026-11-03T12:00:00+02:00', 'ends_at' => '2026-11-03T12:30:00+02:00', 'location' => ' Room 1 ', 'capacity' => 2]),
                sbSlotInput(['id' => $ids[1], 'starts_at' => '2026-11-03T11:00:00Z', 'ends_at' => '2026-11-03T11:45:00Z']),
            ],
        ])->assertOk();

        $slotActivity = SlotActivity::query()->findOrFail($this->activity->id);
        $first = Slot::query()->findOrFail($ids[0]);

        expect($slotActivity->organization_id)->toBe($this->acme->id)
            ->and($slotActivity->slot_duration)->toBe(1800)
            ->and($first->starts_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-03 10:00')
            ->and($first->location)->toBe('Room 1')
            ->and($first->capacity)->toBe(2)
            ->and($first->organization_id)->toBe($this->acme->id);

        $row = DB::selectOne("SELECT to_char(starts_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS starts FROM slots WHERE id = ?", [$ids[0]]);
        expect($row->starts)->toBe('2026-11-03 10:00');

        $this->actingAs($this->teacher)->get(sbUrl("activities/{$this->activity->id}/edit?step=content"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('steps.1.complete', true)
                ->where('data.content.slot_duration', 1800)
                ->where('data.content.slots.0.id', $ids[0])
                ->where('data.content.slots.0.starts_at', '2026-11-03T10:00:00+00:00')
                ->where('data.content.slots.0.capacity', 2)
                ->where('data.content.slots.1.id', $ids[1]));

        // Leaving a slot out removes it; the slot duration may be cleared.
        $this->actingAs($this->teacher)->putJson($this->contentUrl, [
            'slot_duration' => null,
            'slots' => [sbSlotInput(['id' => $ids[1], 'capacity' => 3])],
        ])->assertOk();

        expect($slotActivity->slots()->pluck('capacity', 'id')->all())->toBe([$ids[1] => 3])
            ->and($slotActivity->refresh()->slot_duration)->toBeNull();
    });

    it('uses the slot duration only to pre-fill: a slot of another length is stored as given', function () {
        $id = (string) Str::uuid();

        $this->actingAs($this->teacher)->putJson($this->contentUrl, [
            'slot_duration' => 1800,
            'slots' => [sbSlotInput(['id' => $id, 'starts_at' => '2026-11-03T10:00:00Z', 'ends_at' => '2026-11-03T10:45:00Z'])],
        ])->assertOk();

        expect(Slot::query()->findOrFail($id)->ends_at->utc()->format('H:i'))->toBe('10:45');
    });

    it('refuses invalid slots with problem details and stores nothing', function (array $input, string $field) {
        $this->actingAs($this->teacher)->putJson($this->contentUrl, $input)
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonStructure(['errors' => [$field]]);

        expect(Slot::query()->count())->toBe(0)
            ->and(SlotActivity::query()->count())->toBe(0);
    })->with([
        'no slots' => [['slots' => []], 'slots'],
        '101 slots' => [['slots' => array_map(fn (int $i): array => sbSlotInput(), range(1, 101))], 'slots'],
        'capacity 0' => [['slots' => [sbSlotInput(['capacity' => 0])]], 'slots.0.capacity'],
        'capacity 1001' => [['slots' => [sbSlotInput(['capacity' => 1001])]], 'slots.0.capacity'],
        'ends at the start' => [['slots' => [sbSlotInput(['ends_at' => '2026-11-03T10:00:00Z'])]], 'slots.0.ends_at'],
        'ends before the start' => [['slots' => [sbSlotInput(['ends_at' => '2026-11-03T09:00:00Z'])]], 'slots.0.ends_at'],
        'location of 256 characters' => [['slots' => [sbSlotInput(['location' => str_repeat('x', 256)])]], 'slots.0.location'],
        'no start' => [['slots' => [sbSlotInput(['starts_at' => null])]], 'slots.0.starts_at'],
        'no id' => [['slots' => [array_diff_key(sbSlotInput(), ['id' => true])]], 'slots.0.id'],
        'slot duration under 5 minutes' => [['slot_duration' => 299, 'slots' => [sbSlotInput()]], 'slot_duration'],
        'slot duration over 24 hours' => [['slot_duration' => 86401, 'slots' => [sbSlotInput()]], 'slot_duration'],
    ]);

    it('accepts 100 slots, capacity 1000 and a 255-character location', function () {
        $slots = array_map(fn (int $i): array => sbSlotInput(), range(1, 100));
        $slots[0]['capacity'] = 1000;
        $slots[0]['location'] = str_repeat('x', 255);

        $this->actingAs($this->teacher)->putJson($this->contentUrl, ['slot_duration' => 300, 'slots' => $slots])->assertOk();

        expect(Slot::query()->count())->toBe(100);
    });

    it('refuses the id of a slot of another activity', function () {
        $other = sbActivity(['2026-11-05 09:00' => 1], 'draft');
        $foreign = sbSlot($other, '2026-11-05 09:00');

        $this->actingAs($this->teacher)->putJson($this->contentUrl, ['slots' => [
            sbSlotInput(['id' => $foreign->id, 'capacity' => 5]),
        ]])->assertUnprocessable()->assertJsonStructure(['errors' => ['slots.0.id']]);

        expect($foreign->refresh()->capacity)->toBe(1);
    });

    it('publishes only a slot booking with at least one slot', function () {
        $this->actingAs($this->teacher)->post(sbUrl("activities/{$this->activity->id}/publish"))
            ->assertSessionHasErrors('slots');

        expect($this->activity->refresh()->isDraft())->toBeTrue();

        $this->actingAs($this->teacher)->putJson($this->contentUrl, ['slots' => [sbSlotInput()]])->assertOk();

        $this->actingAs($this->teacher)->post(sbUrl("activities/{$this->activity->id}/publish"))
            ->assertSessionHasNoErrors();

        expect($this->activity->refresh()->isPublished())->toBeTrue();
    });

    it('lets only the owning teacher edit the slots', function () {
        $this->actingAs($this->otherTeacher)->putJson($this->contentUrl, ['slots' => [sbSlotInput()]])->assertNotFound();

        expect(SlotActivity::query()->count())->toBe(0);
    });
});

describe('keeping bookings within the places (SCOPE-028)', function () {
    it('refuses removing a slot with 2 bookings or setting its capacity to 1', function () {
        $slotActivity = sbActivity(['2026-11-03 10:00' => 2, '2026-11-03 11:00' => 1]);
        $booked = sbSlot($slotActivity, '2026-11-03 10:00');
        $free = sbSlot($slotActivity, '2026-11-03 11:00');
        sbHold($booked, $this->student);
        sbHold($booked, $this->classmate);
        $url = sbUrl("activities/{$slotActivity->activity_id}/wizard/content");
        $input = fn (Slot $slot, int $capacity): array => sbSlotInput([
            'id' => $slot->id,
            'starts_at' => $slot->starts_at->toIso8601String(),
            'ends_at' => $slot->ends_at->toIso8601String(),
            'capacity' => $capacity,
        ]);

        $this->actingAs($this->teacher)->putJson($url, ['slots' => [$input($free, 1)]])
            ->assertUnprocessable()->assertJsonStructure(['errors' => ['slots']]);

        $this->actingAs($this->teacher)->putJson($url, ['slots' => [$input($booked, 1), $input($free, 1)]])
            ->assertUnprocessable()->assertJsonStructure(['errors' => ['slots.0.capacity']]);

        expect($slotActivity->slots()->pluck('capacity', 'id')->all())->toBe([$booked->id => 2, $free->id => 1])
            ->and(Booking::query()->count())->toBe(2);

        // More places and an unbooked slot removed are fine.
        $this->actingAs($this->teacher)->putJson($url, ['slots' => [$input($booked, 3)]])->assertOk();

        expect($slotActivity->slots()->pluck('capacity', 'id')->all())->toBe([$booked->id => 3]);
    });
});

describe('booking a slot (UC-016)', function () {
    it('shows every slot with its remaining places and the student\'s own booking, but not who holds the others', function () {
        $slotActivity = sbActivity(['2026-11-03 10:00' => 1, '2026-11-03 11:00' => 3]);
        $later = sbSlot($slotActivity, '2026-11-03 11:00');
        $other = sbHold($later, $this->classmate);
        sbHold($later, $this->student);

        $response = $this->actingAs($this->student)->get(sbUrl("slots/{$slotActivity->activity_id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Activities/SlotBooking/Show')
                ->where('slotBooking.slots.0.starts_at', '2026-11-03T10:00:00+00:00')
                ->where('slotBooking.slots.0.ends_at', '2026-11-03T10:30:00+00:00')
                ->where('slotBooking.slots.0.capacity', 1)
                ->where('slotBooking.slots.0.remaining', 1)
                ->where('slotBooking.slots.0.started', false)
                ->where('slotBooking.slots.1.remaining', 1)
                ->missing('slotBooking.slots.0.holders')
                ->missing('slotBooking.slots.1.holders')
                ->where('booking.slot_id', $later->id)
                ->where('can.book', true)
                ->where('can.viewHolders', false));

        expect((string) $response->getContent())->not->toContain('Bob Classmate')
            ->and((string) $response->getContent())->not->toContain($other->id)
            ->and((string) $response->getContent())->not->toContain($this->classmate->id);
    });

    it('shows the owning teacher every holder', function () {
        $slotActivity = sbActivity(['2026-11-03 10:00' => 2, '2026-11-03 11:00' => 1]);
        sbHold(sbSlot($slotActivity, '2026-11-03 10:00'), $this->student);
        sbHold(sbSlot($slotActivity, '2026-11-03 10:00'), $this->classmate);

        $this->actingAs($this->teacher)->get(sbUrl("slots/{$slotActivity->activity_id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('slotBooking.slots.0.holders', fn ($holders) => collect($holders)->sort()->values()->all() === ['Alice Student', 'Bob Classmate'])
                ->where('slotBooking.slots.0.remaining', 0)
                ->where('slotBooking.slots.1.holders', [])
                ->where('can.viewHolders', true)
                ->where('can.book', false));
    });

    it('books a slot with free places', function () {
        $slotActivity = sbActivity();

        sbBook($this->student, $slotActivity, sbSlot($slotActivity, '2026-11-03 10:00'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(sbUrl("slots/{$slotActivity->activity_id}"));

        $booking = Booking::query()->sole();

        expect($booking->student_id)->toBe($this->student->id)
            ->and($booking->group_id)->toBeNull()
            ->and($booking->organization_id)->toBe($this->acme->id)
            ->and(sbRemaining($this->student, $slotActivity))->toBe(['10:00' => 0, '11:00' => 2]);
    });

    it('refuses a third student in a slot of capacity 2 holding 2 bookings', function () {
        $slotActivity = sbActivity(['2026-11-03 10:00' => 2]);
        $slot = sbSlot($slotActivity, '2026-11-03 10:00');
        sbBook($this->student, $slotActivity, $slot)->assertSessionHasNoErrors();
        sbBook($this->classmate, $slotActivity, $slot)->assertSessionHasNoErrors();

        sbBook($this->third, $slotActivity, $slot)
            ->assertSessionHasErrors(['slot' => __('activities.slots.book.refused.full')]);

        expect(Booking::query()->count())->toBe(2)
            ->and(Booking::query()->where('student_id', $this->third->id)->exists())->toBeFalse();
    });

    it('releases the first booking when the student books another slot of the same activity', function () {
        $slotActivity = sbActivity(['2026-11-03 10:00' => 1, '2026-11-03 11:00' => 2]);
        sbBook($this->student, $slotActivity, sbSlot($slotActivity, '2026-11-03 10:00'))->assertSessionHasNoErrors();
        $first = Booking::query()->sole();

        sbBook($this->student, $slotActivity, sbSlot($slotActivity, '2026-11-03 11:00'))->assertSessionHasNoErrors();

        // The row moves: one booking per student, the first slot regains its place.
        expect(Booking::query()->count())->toBe(1)
            ->and(Booking::query()->sole()->id)->toBe($first->id)
            ->and(sbHolders($slotActivity))->toBe(['10:00' => [], '11:00' => [$this->student->id]])
            ->and(sbRemaining($this->student, $slotActivity))->toBe(['10:00' => 1, '11:00' => 1]);

        sbBook($this->classmate, $slotActivity, sbSlot($slotActivity, '2026-11-03 10:00'))->assertSessionHasNoErrors();
    });

    it('refuses moving to a full slot and keeps the current booking', function () {
        $slotActivity = sbActivity(['2026-11-03 10:00' => 1, '2026-11-03 11:00' => 2]);
        sbHold(sbSlot($slotActivity, '2026-11-03 10:00'), $this->classmate);
        sbBook($this->student, $slotActivity, sbSlot($slotActivity, '2026-11-03 11:00'))->assertSessionHasNoErrors();

        sbBook($this->student, $slotActivity, sbSlot($slotActivity, '2026-11-03 10:00'))->assertSessionHasErrors('slot');

        expect(sbHolders($slotActivity))->toBe(['10:00' => [$this->classmate->id], '11:00' => [$this->student->id]]);
    });

    it('deletes the booking on cancellation and the slot has one more free place', function () {
        $slotActivity = sbActivity(['2026-11-03 10:00' => 2], 'published', ['deadline_at' => now()->addDays(3)]);
        $slot = sbSlot($slotActivity, '2026-11-03 10:00');
        sbHold($slot, $this->classmate);
        sbBook($this->student, $slotActivity, $slot)->assertSessionHasNoErrors();

        expect(sbRemaining($this->student, $slotActivity))->toBe(['10:00' => 0]);

        sbCancel($this->student, $slotActivity)->assertSessionHasNoErrors();

        expect(Booking::query()->where('student_id', $this->student->id)->exists())->toBeFalse()
            ->and(Booking::query()->count())->toBe(1)
            ->and(sbRemaining($this->student, $slotActivity))->toBe(['10:00' => 1]);

        sbCancel($this->student, $slotActivity)->assertSessionHasErrors(['slot' => __('activities.slots.book.refused.no_booking')]);
    });

    it('refuses booking a slot that has started while the deadline is still ahead', function () {
        $slotActivity = sbActivity(['2026-11-02 09:30' => 5, '2026-11-03 10:00' => 5], 'published', ['deadline_at' => now()->addDays(3)]);

        sbBook($this->student, $slotActivity, sbSlot($slotActivity, '2026-11-02 09:30'))
            ->assertSessionHasErrors(['slot' => __('activities.slots.book.refused.started')]);

        expect(Booking::query()->count())->toBe(0);

        $this->actingAs($this->student)->get(sbUrl("slots/{$slotActivity->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('slotBooking.is_open', true)
                ->where('slotBooking.slots.0.started', true)
                ->where('slotBooking.slots.1.started', false));
    });

    it('refuses changing or cancelling a booking in a slot that has started', function () {
        $slotActivity = sbActivity(['2026-11-02 11:00' => 5, '2026-11-03 10:00' => 5], 'published', ['deadline_at' => now()->addDays(3)]);
        $early = sbSlot($slotActivity, '2026-11-02 11:00');
        sbBook($this->student, $slotActivity, $early)->assertSessionHasNoErrors();

        // The slot starts at 11:00; it is now 11:00, the deadline still ahead.
        $this->travelTo(CarbonImmutable::parse('2026-11-02 11:00:00'));

        sbBook($this->student, $slotActivity, sbSlot($slotActivity, '2026-11-03 10:00'))
            ->assertSessionHasErrors(['slot' => __('activities.slots.book.refused.current_started')]);
        sbCancel($this->student, $slotActivity)
            ->assertSessionHasErrors(['slot' => __('activities.slots.book.refused.current_started')]);

        expect(sbHolders($slotActivity))->toBe(['11:00' => [$this->student->id], '10:00' => []]);
    });

    it('refuses booking, changing and cancelling after the deadline and keeps the booking', function () {
        $slotActivity = sbActivity(['2026-11-03 10:00' => 1, '2026-11-03 11:00' => 1], 'published', ['deadline_at' => now()->addHour()]);
        sbBook($this->student, $slotActivity, sbSlot($slotActivity, '2026-11-03 10:00'))->assertSessionHasNoErrors();

        $this->travel(2)->hours();

        sbCancel($this->student, $slotActivity)
            ->assertSessionHasErrors(['slot' => __('activities.slots.book.refused.deadline_passed')]);
        sbBook($this->student, $slotActivity, sbSlot($slotActivity, '2026-11-03 11:00'))->assertSessionHasErrors('slot');
        sbBook($this->classmate, $slotActivity, sbSlot($slotActivity, '2026-11-03 11:00'))->assertSessionHasErrors('slot');

        expect(sbHolders($slotActivity))->toBe(['10:00' => [$this->student->id], '11:00' => []]);

        $this->actingAs($this->student)->get(sbUrl("slots/{$slotActivity->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('slotBooking.is_open', false)
                ->where('can.book', false));
    });

    it('refuses booking and cancelling once the activity is closed', function () {
        $slotActivity = sbActivity(['2026-11-03 10:00' => 1, '2026-11-03 11:00' => 1]);
        sbBook($this->student, $slotActivity, sbSlot($slotActivity, '2026-11-03 10:00'))->assertSessionHasNoErrors();
        $this->actingAs($this->teacher)->post(sbUrl("activities/{$slotActivity->activity_id}/close"));

        sbCancel($this->student, $slotActivity)
            ->assertSessionHasErrors(['slot' => __('activities.slots.book.refused.closed')]);
        sbBook($this->classmate, $slotActivity, sbSlot($slotActivity, '2026-11-03 11:00'))->assertSessionHasErrors('slot');

        expect(sbHolders($slotActivity))->toBe(['10:00' => [$this->student->id], '11:00' => []]);
    });

    it('refuses a slot of another activity', function () {
        $slotActivity = sbActivity();
        $other = sbActivity(['2026-11-05 10:00' => 5]);

        sbBook($this->student, $slotActivity, sbSlot($other, '2026-11-05 10:00'))->assertSessionHasErrors('slot');
        sbBook($this->student, $slotActivity, 'not-a-uuid')->assertSessionHasErrors('slot_id');

        expect(Booking::query()->count())->toBe(0);
    });

    it('does not exist as a draft for students', function () {
        $slotActivity = sbActivity(['2026-11-03 10:00' => 1], 'draft');

        $this->actingAs($this->student)->get(sbUrl("slots/{$slotActivity->activity_id}"))->assertNotFound();
        sbBook($this->student, $slotActivity, sbSlot($slotActivity, '2026-11-03 10:00'))->assertNotFound();

        expect(Booking::query()->count())->toBe(0);
    });

    it('lets only enrolled students book', function () {
        $slotActivity = sbActivity();

        sbBook($this->outsider, $slotActivity, sbSlot($slotActivity, '2026-11-03 10:00'))->assertForbidden();
        sbBook($this->teacher, $slotActivity, sbSlot($slotActivity, '2026-11-03 10:00'))->assertForbidden();

        expect(Booking::query()->count())->toBe(0);
    });

    it('answers 404 for an activity that is no slot booking or of another organisation', function () {
        $poll = Activity::factory()->ofKind(ActivityKind::Poll)->for($this->space, 'courseSpace')->published()->create();
        $this->actingAs($this->student)->get(sbUrl("slots/{$poll->id}"))->assertNotFound();

        $globex = $this->createOrganization(['subdomain' => 'globex']);
        $this->actingAsTenant($globex);
        $foreignTeacher = User::factory()->teacher()->create();
        $foreignSpace = CourseSpace::factory()->for($foreignTeacher, 'teacher')->create();
        $foreign = SlotActivity::factory()->for(
            Activity::factory()->ofKind(ActivityKind::SlotBooking)->for($foreignSpace, 'courseSpace')->published()->create(),
            'activity',
        )->withSlots(['2026-11-03 10:00' => 3])->create();
        $foreignSlot = Slot::query()->where('slot_activity_id', $foreign->activity_id)->sole();
        $this->actingAsTenant($this->acme);

        $this->actingAs($this->student)->get(sbUrl("slots/{$foreign->activity_id}"))->assertNotFound();
        sbBook($this->student, $foreign, $foreignSlot->id)->assertNotFound();

        expect(Booking::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('lists the bookings in the teacher\'s submissions overview', function () {
        $slotActivity = sbActivity();
        sbBook($this->student, $slotActivity, sbSlot($slotActivity, '2026-11-03 10:00'))->assertSessionHasNoErrors();

        $this->actingAs($this->teacher)->get(sbUrl("activities/{$slotActivity->activity_id}/submissions"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.participant', 'Alice Student')
                ->where('submissions.0.status', 'submitted')
                ->where('submissions.0.url', sbUrl("slots/{$slotActivity->activity_id}")));
    });

    it('counts only current bookings as submissions (SCOPE-029)', function () {
        $slotActivity = sbActivity();
        $type = app(ActivityTypes::class)->get(ActivityKind::SlotBooking);

        expect($type->hasSubmissions($slotActivity->activity))->toBeFalse();

        sbBook($this->student, $slotActivity, sbSlot($slotActivity, '2026-11-03 10:00'))->assertSessionHasNoErrors();

        expect($type->hasSubmissions($slotActivity->activity))->toBeTrue();

        sbCancel($this->student, $slotActivity)->assertSessionHasNoErrors();

        expect($type->hasSubmissions($slotActivity->activity))->toBeFalse();
    });

    it('deletes the student\'s bookings with their account (SEC-003)', function () {
        $slotActivity = sbActivity();
        sbHold(sbSlot($slotActivity, '2026-11-03 10:00'), $this->student);

        $this->student->forceDelete();

        expect(Booking::query()->count())->toBe(0);
    });
});

describe('group slot booking (SCOPE-012, SCOPE-025)', function () {
    it('lets one member book for the group, and every member sees it as the group\'s booking', function () {
        $slotActivity = sbGroupActivity(3);
        $group = sbGroup('Team A', [$this->student, $this->classmate]);
        $later = sbSlot($slotActivity, '2026-11-03 11:00');

        sbBook($this->student, $slotActivity, $later, ['group_id' => $group->id])->assertSessionHasNoErrors();

        $booking = Booking::query()->sole();

        expect($booking->group_id)->toBe($group->id)
            ->and($booking->student_id)->toBeNull();

        $this->actingAs($this->classmate)->get(sbUrl("slots/{$slotActivity->activity_id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('slotBooking.is_group', true)
                ->has('groups', 1)
                ->where('groups.0.id', $group->id)
                ->where('groups.0.slot_id', $later->id));

        // Another member may move or cancel the group's booking.
        sbBook($this->classmate, $slotActivity, sbSlot($slotActivity, '2026-11-03 10:00'), ['group_id' => $group->id])
            ->assertSessionHasNoErrors();

        expect(sbHolders($slotActivity))->toBe(['10:00' => [$group->id], '11:00' => []]);

        sbCancel($this->student, $slotActivity, ['group_id' => $group->id])->assertSessionHasNoErrors();

        expect(Booking::query()->count())->toBe(0);
    });

    it('lets a student in G1 and G2 pick which group books, and the booking belongs to that group', function () {
        $slotActivity = sbGroupActivity(3);
        $g1 = sbGroup('G1', [$this->student, $this->classmate]);
        $g2 = sbGroup('G2', [$this->third, $this->student]);

        $this->actingAs($this->student)->get(sbUrl("slots/{$slotActivity->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->has('groups', 2)
                ->where('groups.0.id', $g1->id)
                ->where('groups.1.id', $g2->id));

        sbBook($this->student, $slotActivity, sbSlot($slotActivity, '2026-11-03 10:00'), ['group_id' => $g2->id])
            ->assertSessionHasNoErrors();

        $booking = Booking::query()->sole();

        expect($booking->group_id)->toBe($g2->id)
            ->and($booking->student_id)->toBeNull();

        $this->actingAs($this->third)->get(sbUrl("slots/{$slotActivity->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page->where('groups.0.slot_id', $booking->slot_id));
        $this->actingAs($this->classmate)->get(sbUrl("slots/{$slotActivity->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('groups.0.id', $g1->id)
                ->where('groups.0.slot_id', null)
                ->where('groups.0.in_other_group', true));
    });

    it('refuses G2 booking when G1 holds a booking and a member is in both', function () {
        $slotActivity = sbGroupActivity(3);
        $g1 = sbGroup('G1', [$this->student, $this->classmate]);
        $g2 = sbGroup('G2', [$this->third, $this->student]);
        sbBook($this->classmate, $slotActivity, sbSlot($slotActivity, '2026-11-03 10:00'), ['group_id' => $g1->id])->assertSessionHasNoErrors();

        sbBook($this->third, $slotActivity, sbSlot($slotActivity, '2026-11-03 11:00'), ['group_id' => $g2->id])
            ->assertSessionHasErrors(['group_id' => __('activities.slots.book.refused.member_in_other_group')]);
        sbBook($this->student, $slotActivity, sbSlot($slotActivity, '2026-11-03 11:00'), ['group_id' => $g2->id])
            ->assertSessionHasErrors('group_id');

        expect(sbHolders($slotActivity))->toBe(['10:00' => [$g1->id], '11:00' => []]);
    });

    it('refuses a group with more current members than the group size', function () {
        $slotActivity = sbGroupActivity(2);
        $group = sbGroup('Big', [$this->student, $this->classmate, $this->third]);

        sbBook($this->student, $slotActivity, sbSlot($slotActivity, '2026-11-03 11:00'), ['group_id' => $group->id])
            ->assertSessionHasErrors(['group_id' => __('activities.slots.book.refused.group_too_large', ['size' => 2])]);

        expect(Booking::query()->count())->toBe(0);
    });

    it('requires one of the student\'s own groups of the space', function () {
        $slotActivity = sbGroupActivity(3);
        $own = sbGroup('Own', [$this->student]);
        $foreign = sbGroup('Others', [$this->classmate, $this->third]);
        $slot = sbSlot($slotActivity, '2026-11-03 11:00');

        sbBook($this->student, $slotActivity, $slot)
            ->assertSessionHasErrors(['group_id' => __('activities.slots.book.refused.choose_group')]);
        sbBook($this->student, $slotActivity, $slot, ['group_id' => $foreign->id])->assertSessionHasErrors('group_id');

        expect(Booking::query()->count())->toBe(0);

        sbBook($this->student, $slotActivity, $slot, ['group_id' => $own->id])->assertSessionHasNoErrors();
    });

    it('keeps the booking of a deleted group and lets nobody act for it', function () {
        $slotActivity = sbGroupActivity(3);
        $group = sbGroup('Gone', [$this->student]);
        $booking = sbHold(sbSlot($slotActivity, '2026-11-03 10:00'), group: $group);
        $group->delete();

        sbCancel($this->student, $slotActivity, ['group_id' => $group->id])->assertSessionHasErrors('group_id');
        sbBook($this->student, $slotActivity, sbSlot($slotActivity, '2026-11-03 11:00'), ['group_id' => $group->id])->assertSessionHasErrors('group_id');

        expect($booking->fresh()?->slot_id)->toBe(sbSlot($slotActivity, '2026-11-03 10:00')->id);
    });
});

describe('OWN-010 on bookings', function () {
    it('denies a student any change to a booking that is not theirs or their group\'s, and nothing changes', function () {
        $slotActivity = sbGroupActivity(3);
        $theirs = sbGroup('Theirs', [$this->classmate, $this->third]);
        $mine = sbGroup('Mine', [$this->student]);
        $early = sbSlot($slotActivity, '2026-11-03 10:00');
        $later = sbSlot($slotActivity, '2026-11-03 11:00');
        $booking = sbHold($early, group: $theirs);

        sbCancel($this->student, $slotActivity, ['group_id' => $theirs->id])->assertSessionHasErrors('group_id');
        sbBook($this->student, $slotActivity, $later, ['group_id' => $theirs->id])->assertSessionHasErrors('group_id');
        // Acting for their own group never touches the other group's booking.
        sbCancel($this->student, $slotActivity, ['group_id' => $mine->id])->assertSessionHasErrors('slot');

        expect($booking->fresh()?->slot_id)->toBe($early->id)
            ->and(Booking::query()->count())->toBe(1);

        $gate = Gate::forUser($this->student);

        expect($gate->allows('view', $booking))->toBeFalse()
            ->and($gate->allows('update', $booking))->toBeFalse()
            ->and($gate->allows('delete', $booking))->toBeFalse()
            ->and($gate->allows('viewAny', Booking::class))->toBeFalse()
            ->and(Gate::forUser($this->classmate)->allows('update', $booking))->toBeTrue();
    });

    it('denies a student another student\'s individual booking', function () {
        $slotActivity = sbActivity(['2026-11-03 10:00' => 2]);
        $booking = sbHold(sbSlot($slotActivity, '2026-11-03 10:00'), $this->classmate);

        // Cancelling only ever addresses the student's own booking: they have none.
        sbCancel($this->student, $slotActivity)->assertSessionHasErrors('slot');

        expect($booking->fresh())->not->toBeNull();

        $gate = Gate::forUser($this->student);

        expect($gate->allows('view', $booking))->toBeFalse()
            ->and($gate->allows('update', $booking))->toBeFalse()
            ->and($gate->allows('delete', $booking))->toBeFalse()
            ->and(Gate::forUser($this->classmate)->allows('view', $booking))->toBeTrue();
    });

    it('denies a former member the group\'s booking', function () {
        $slotActivity = sbGroupActivity(3);
        $group = sbGroup('Team', [$this->classmate, $this->student]);
        $booking = sbHold(sbSlot($slotActivity, '2026-11-03 10:00'), group: $group);
        GroupMembership::query()->where('group_id', $group->id)->where('student_id', $this->student->id)->sole()
            ->transitionTo(GroupMembershipStatus::Left);

        sbCancel($this->student, $slotActivity, ['group_id' => $group->id])->assertSessionHasErrors('group_id');

        expect($booking->fresh())->not->toBeNull()
            ->and(Gate::forUser($this->student)->allows('delete', $booking))->toBeFalse();
    });

    it('lets the owning teacher see but change no booking', function () {
        $slotActivity = sbActivity(['2026-11-03 10:00' => 2, '2026-11-03 11:00' => 1]);
        $booking = sbHold(sbSlot($slotActivity, '2026-11-03 10:00'), $this->student);

        sbCancel($this->teacher, $slotActivity)->assertForbidden();
        sbBook($this->teacher, $slotActivity, sbSlot($slotActivity, '2026-11-03 11:00'))->assertForbidden();

        $gate = Gate::forUser($this->teacher);

        expect($gate->allows('view', $booking))->toBeTrue()
            ->and($gate->allows('update', $booking))->toBeFalse()
            ->and($gate->allows('delete', $booking))->toBeFalse()
            ->and($booking->fresh()?->slot_id)->toBe(sbSlot($slotActivity, '2026-11-03 10:00')->id);
    });

    it('denies another teacher and the org admin the bookings of an activity they do not own', function (string $who) {
        $viewer = $who === 'teacher' ? $this->otherTeacher : $this->admin;
        $slotActivity = sbActivity(['2026-11-03 10:00' => 2]);
        $booking = sbHold(sbSlot($slotActivity, '2026-11-03 10:00'), $this->student);

        $this->actingAs($viewer)->get(sbUrl("slots/{$slotActivity->activity_id}"))->assertForbidden();
        $this->actingAs($viewer)->get(sbUrl("activities/{$slotActivity->activity_id}/submissions"))->assertForbidden();
        sbCancel($viewer, $slotActivity)->assertForbidden();

        $gate = Gate::forUser($viewer);

        expect($gate->allows('view', $booking))->toBeFalse()
            ->and($gate->allows('update', $booking))->toBeFalse()
            ->and($gate->allows('delete', $booking))->toBeFalse()
            ->and($gate->allows('viewBookings', $slotActivity))->toBeFalse()
            ->and($booking->fresh())->not->toBeNull();
    })->with(['teacher', 'org admin']);
});

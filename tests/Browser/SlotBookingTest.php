<?php

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Booking;
use App\Modules\Activities\Models\Slot;
use App\Modules\Activities\Models\SlotActivity;
use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Browser flow of UC-016: a student opens a published slot booking from its
| activity page, books a slot, moves to another one and cancels; in a
| group activity one member books for the group and another member sees
| it; the teacher builds the slots in the wizard (the slot length pre-fills
| the end, which can still be changed) and sees who holds which slot.
|
| The browser test server always answers as http://127.0.0.1:<port>, so the
| organisation "127" on the apex domain "0.0.1" stands in for a subdomain.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => '0.0.1', 'app.locale' => 'en']);

    $this->organization = $this->createOrganization(['subdomain' => '127', 'name' => 'Acme School']);
    $this->actingAsTenant($this->organization);
    $this->teacher = User::factory()->teacher()->create(['locale' => 'en']);
    $this->student = User::factory()->student()->create(['locale' => 'en', 'name' => 'Alice Student']);
    $this->classmate = User::factory()->student()->create(['locale' => 'en', 'name' => 'Bob Classmate']);
    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->classmate, 'student')->create();
});

function bsSlotActivity(array $attributes = []): Activity
{
    $activity = Activity::factory()->ofKind(ActivityKind::SlotBooking)->for(test()->space, 'courseSpace')->published()
        ->create(['title' => 'Presentation slots', 'deadline_at' => now()->addWeek(), ...$attributes]);
    SlotActivity::factory()->for($activity, 'activity')->withSlots(['+2 days 10:00' => 1, '+2 days 11:00' => 2])->create();

    return $activity;
}

it('books, moves and cancels a slot', function () {
    $activity = bsSlotActivity();
    $this->actingAs($this->student);

    visit("/activities/{$activity->id}")
        ->click('@activity-open-slots')
        ->assertSee('Presentation slots')
        ->assertSeeIn('@slots-current', 'You have not booked a slot yet.')
        ->assertSeeIn('@slot-remaining-0', '1 place left of 1')
        ->click('@slot-book-0')
        ->assertVisible('@slot-booked-0')
        ->assertSeeIn('@slot-remaining-0', 'Full')
        ->click('@slot-book-1')
        ->assertVisible('@slot-booked-1')
        ->assertSeeIn('@slot-remaining-0', '1 place left of 1')
        ->assertSeeIn('@slot-remaining-1', '1 place left of 2')
        ->click('@slots-cancel')
        ->assertSeeIn('@slots-current', 'You have not booked a slot yet.')
        ->assertNoJavaScriptErrors();

    expect(Booking::query()->count())->toBe(0);
});

it('lets one member book for the group and shows it to the other member', function () {
    $activity = bsSlotActivity(['is_group' => true, 'group_size' => 3]);
    $group = Group::factory()->for($this->space, 'courseSpace')->ownedBy($this->student)->create(['name' => 'Team A']);
    GroupMembership::factory()->for($group, 'group')->for($this->student, 'student')->create(['status' => GroupMembershipStatus::Member]);
    GroupMembership::factory()->for($group, 'group')->for($this->classmate, 'student')->create()->transitionTo(GroupMembershipStatus::Member);

    $this->actingAs($this->student);
    visit("/slots/{$activity->id}")
        ->assertVisible('@slots-group-activity')
        ->assertSeeIn('@slots-group-booking-0', 'Team A has not booked a slot yet.')
        ->click('@slot-book-1')
        ->assertSeeIn('@slots-group-booking-0', 'Slot of Team A')
        ->assertNoJavaScriptErrors();

    $this->actingAs($this->classmate);
    visit("/slots/{$activity->id}")
        ->assertSeeIn('@slots-group-booking-0', 'Slot of Team A')
        ->assertVisible('@slot-booked-1')
        ->assertNoJavaScriptErrors();

    expect(Booking::query()->sole()->group_id)->toBe($group->id);
});

it('pre-fills the end from the slot length, keeps a changed end, and shows the teacher who holds the slots', function () {
    $this->actingAs($this->teacher);
    $day = now()->addDays(3)->format('Y-m-d');

    visit('/activities/create')
        ->select('@wizard-course-space', $this->space->id)
        ->select('@wizard-type', 'slot_booking')
        ->fill('@wizard-title', 'Exam slots')
        ->click('@wizard-next')
        ->assertVisible('@slot-editor')
        ->fill('@slot-duration', '30')
        ->fill('@slot-start-0', "{$day}T10:00")
        ->assertValue('@slot-end-0', "{$day}T10:30")
        ->fill('@slot-end-0', "{$day}T10:40")
        ->fill('@slot-capacity-0', '2')
        ->fill('@slot-location-0', 'Room 101')
        ->click('@wizard-next')
        ->assertVisible('@wizard-review')
        ->click('@wizard-publish')
        ->assertNoJavaScriptErrors();

    $activity = Activity::query()->where('title', 'Exam slots')->sole();
    $slot = Slot::query()->where('slot_activity_id', $activity->id)->sole();

    expect($activity->isPublished())->toBeTrue()
        ->and(SlotActivity::query()->findOrFail($activity->id)->slot_duration)->toBe(1800)
        ->and((int) $slot->starts_at->diffInMinutes($slot->ends_at))->toBe(40)
        ->and($slot->capacity)->toBe(2)
        ->and($slot->location)->toBe('Room 101');

    $booking = new Booking;
    $booking->slot_id = $slot->id;
    $booking->student_id = $this->student->id;
    $booking->save();

    visit("/activities/{$activity->id}")
        ->click('@activity-open-slots')
        ->assertSeeIn('@slot-holders-0', 'Alice Student')
        ->assertSeeIn('@slot-location-0', 'Room 101')
        ->assertMissing('@slot-book-0')
        ->assertNoJavaScriptErrors();
});

<?php

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Poll;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Browser flow of UC-040: the owning teacher deletes an activity from its
| page after confirming in a dialog; cancelling the dialog keeps it. The
| activity then disappears from the list and offers no restore.
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
    $this->student = User::factory()->student()->create(['locale' => 'en']);
    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();

    $this->activity = Activity::factory()->ofKind(ActivityKind::Poll)->for($this->space, 'courseSpace')->published()
        ->create(['title' => 'Lunch poll']);
    Poll::factory()->for($this->activity, 'activity')->withOptions(['Pizza', 'Salad'])->create();
});

it('deletes an activity after the teacher confirms it, and keeps it when they cancel', function () {
    $this->actingAs($this->teacher);

    visit("/activities/{$this->activity->id}")
        ->click('@activity-delete')
        ->assertSeeIn('@delete-dialog', 'Delete this activity?')
        ->assertSeeIn('@delete-dialog', '“Lunch poll” will be deleted')
        ->click('@delete-cancel')
        ->assertMissing('@delete-dialog')
        ->assertSeeIn('@activity-title', 'Lunch poll')
        ->click('@activity-delete')
        ->click('@delete-confirm')
        ->assertSee('Activity deleted.')
        ->assertDontSee('Lunch poll')
        ->assertDontSee('Restore')
        ->assertNoJavaScriptErrors();

    expect(Activity::query()->find($this->activity->id))->toBeNull()
        ->and(Activity::withTrashed()->find($this->activity->id)?->trashed())->toBeTrue();
});

it('offers no delete button once the activity has submissions', function () {
    $this->actingAs($this->student);
    visit("/polls/{$this->activity->id}")
        ->click('@poll-choice-1')
        ->click('@poll-submit')
        ->assertVisible('@poll-voted')
        ->assertNoJavaScriptErrors();

    $this->actingAs($this->teacher);
    visit("/activities/{$this->activity->id}")
        ->assertSeeIn('@activity-title', 'Lunch poll')
        ->assertMissing('@activity-delete')
        ->assertNoJavaScriptErrors();
});

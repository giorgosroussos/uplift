<?php

use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Models\NotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Browser flow of UC-007: the bell shows the unread count; the student opens
| the notification centre, opens a notification, marks all as read and turns
| notification emails off.
|
| The browser test server always answers as http://127.0.0.1:<port>, so the
| organisation "127" on the apex domain "0.0.1" stands in for a subdomain.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => '0.0.1', 'app.locale' => 'en']);

    $this->organization = $this->createOrganization(['subdomain' => '127', 'name' => 'Acme School', 'default_locale' => 'en']);
    $this->actingAsTenant($this->organization);
    $this->teacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create();
    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();
});

it('shows a posted announcement in the notification centre and opens its course space', function () {
    $this->actingAs($this->teacher);

    visit("/courses/{$this->space->id}")
        ->fill('@announcement-title', 'Exam room')
        ->fill('@announcement-body', 'The exam takes place in room A1.')
        ->click('@announcement-submit')
        ->assertSee('Announcement published.')
        ->assertMissing('@notification-count');

    $this->actingAs($this->student);

    visit('/dashboard')
        ->assertSeeIn('@notification-count', '1')
        ->click('@notification-bell')
        ->assertSee('Today')
        ->assertSee('New announcement in Algorithms: Exam room')
        ->click('@notification-open')
        ->assertPathIs("/courses/{$this->space->id}")
        ->assertSeeIn('@announcement-heading', 'Exam room')
        ->assertMissing('@notification-count')
        ->assertNoJavaScriptErrors();

    expect(Notification::query()->sole()->read_at)->not->toBeNull();
});

it('shows activity and grade notifications in the notification centre', function () {
    $data = [
        'course_space_id' => $this->space->id,
        'activity_id' => 'activity-id',
        'course_space_title' => 'Algorithms',
        'activity_title' => 'Sorting quiz',
    ];
    Notification::factory()->for($this->student)->create(['kind' => 'activity_published', 'data' => $data]);
    Notification::factory()->for($this->student)->create(['kind' => 'grade_finalised', 'data' => [...$data, 'attempt_id' => 'attempt-id']]);
    $this->actingAs($this->student);

    visit('/notifications')
        ->assertSee('New activity in Algorithms: Sorting quiz')
        ->assertSee('Your attempt at Sorting quiz in Algorithms has been graded')
        ->assertNoJavaScriptErrors();
});

it('marks all notifications as read', function () {
    Notification::factory()->for($this->student)->count(3)->create();
    $this->actingAs($this->student);

    visit('/notifications')
        ->assertSeeIn('@notification-count', '3')
        ->click('@mark-all-read')
        ->assertMissing('@notification-count')
        ->assertNoJavaScriptErrors();

    expect(Notification::query()->whereNull('read_at')->count())->toBe(0);
});

it('turns notification emails off in the settings', function () {
    $this->actingAs($this->student);

    visit('/settings/notifications')
        ->assertSee('Send me notification emails')
        ->click('@email-enabled')
        ->click('@save-notification-settings')
        ->assertSee('Notification settings saved.')
        ->assertNoJavaScriptErrors();

    expect(NotificationPreference::emailEnabledFor($this->student))->toBeFalse();
});

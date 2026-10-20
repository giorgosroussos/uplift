<?php

use App\Modules\Courses\Models\Announcement;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Browser flow of UC-008: the owning teacher posts an announcement in a
| course space; an enrolled student reads it on the course space page.
|
| The browser test server always answers as http://127.0.0.1:<port>, so the
| organisation "127" on the apex domain "0.0.1" stands in for a subdomain.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => '0.0.1', 'app.locale' => 'en']);

    $this->organization = $this->createOrganization(['subdomain' => '127', 'name' => 'Acme School']);
    $this->actingAsTenant($this->organization);
    $this->teacher = User::factory()->teacher()->create(['name' => 'Ada Teacher']);
    $this->student = User::factory()->student()->create();
    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();
});

it('lets the owning teacher post an announcement that an enrolled student reads', function () {
    $this->actingAs($this->teacher);

    visit("/courses/{$this->space->id}")
        ->assertSee('There are no announcements yet.')
        ->fill('@announcement-title', 'Exam room')
        ->fill('@announcement-body', 'The exam takes place in room A1.')
        ->click('@announcement-submit')
        ->assertSee('Announcement published.')
        ->assertSeeIn('@announcement-heading', 'Exam room')
        ->assertNoJavaScriptErrors();

    expect(Announcement::query()->sole()->title)->toBe('Exam room');

    $this->actingAs($this->student);

    visit("/courses/{$this->space->id}")
        ->assertSeeIn('@announcement-heading', 'Exam room')
        ->assertSee('The exam takes place in room A1.')
        ->assertSee('By Ada Teacher')
        ->assertMissing('@announcement-form')
        ->assertMissing('@announcement-delete')
        ->assertNoJavaScriptErrors();
});

it('lets the owning teacher delete an announcement after confirming', function () {
    Announcement::factory()->for($this->space, 'courseSpace')->create(['title' => 'Old news']);
    $this->actingAs($this->teacher);

    visit("/courses/{$this->space->id}")
        ->assertSee('Old news')
        ->click('@announcement-delete')
        ->click('@announcement-delete-confirm')
        ->assertSee('Announcement deleted.')
        ->assertSee('There are no announcements yet.')
        ->assertNoJavaScriptErrors();

    expect(Announcement::query()->count())->toBe(0);
});

<?php

use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Browser flows of UC-021 (teacher creates a course space with an enrolment
| key) and UC-011 (student finds it in the catalogue and enrols with the key).
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
});

it('lets a teacher create a course space with an enrolment key', function () {
    $this->actingAs($this->teacher);

    visit('/courses')
        ->click('@create-course')
        ->assertPathIs('/courses/create')
        ->fill('@course-title', 'Algorithms')
        ->fill('@course-description', 'Graphs and trees')
        ->click('@policy-key')
        ->fill('@course-enrolment-key', 'K7P2Q')
        ->click('@course-submit')
        ->assertSee('Algorithms')
        ->assertSee('K7P2Q')
        ->assertNoJavaScriptErrors();

    $space = CourseSpace::query()->sole();
    expect($space->teacher_id)->toBe($this->teacher->id);
});

it('lets a student find a course space in the catalogue and enrol with the key', function () {
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->withKey('K7P2Q')
        ->create(['title' => 'Algorithms']);
    $this->actingAs($this->student);

    visit('/catalogue')
        ->assertSee('Algorithms')
        ->click('Algorithms')
        ->assertPathIs("/courses/{$space->id}")
        ->fill('@enrolment-key', 'WRONG')
        ->click('@enrol-button')
        ->assertSee('The enrolment key is not correct.')
        ->fill('@enrolment-key', 'K7P2Q')
        ->click('@enrol-button')
        ->assertVisible('@enrolled')
        ->assertNoJavaScriptErrors();

    expect(Enrolment::query()->where('student_id', $this->student->id)->count())->toBe(1);
});

it('asks the teacher to confirm archiving, stating that all students are unenrolled', function () {
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);
    Enrolment::factory()->for($space, 'courseSpace')->for($this->student, 'student')->create();
    $this->actingAs($this->teacher);

    $page = visit("/courses/{$space->id}")
        ->click('@course-status-toggle')
        ->assertVisible('@archive-dialog')
        ->assertSee('All students are unenrolled (1 enrolled now)')
        ->click('Cancel')
        ->assertMissing('@archive-dialog');

    expect($space->refresh()->isActive())->toBeTrue()
        ->and($space->enrolments()->count())->toBe(1);

    $page->click('@course-status-toggle')
        ->click('@archive-confirm')
        ->assertSee('Course space archived.')
        ->assertVisible('@archived-notice')
        ->assertNoJavaScriptErrors();

    expect($space->refresh()->isArchived())->toBeTrue()
        ->and($space->enrolments()->count())->toBe(0);
});

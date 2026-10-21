<?php

use App\Modules\Activities\Enums\ActivityStatus;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Activities\FakeQuizType;

/*
| Browser flow of UC-022 (wizard shell): the teacher fills in the basics,
| the first autosave creates the draft and the wizard continues with the
| type's settings; returning to the wizard restores the saved basics. An
| enrolled student does not see the draft. The real quiz steps come with
| the quiz type (P3-T3); the test type has no Vue pages.
|
| The browser test server always answers as http://127.0.0.1:<port>, so the
| organisation "127" on the apex domain "0.0.1" stands in for a subdomain.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => '0.0.1', 'app.locale' => 'en']);
    FakeQuizType::reset();
    app(ActivityTypes::class)->register(FakeQuizType::class);

    $this->organization = $this->createOrganization(['subdomain' => '127', 'name' => 'Acme School']);
    $this->actingAsTenant($this->organization);
    $this->teacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create();
    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();
});

it('creates a draft from the basics and restores them when the teacher returns', function () {
    $this->actingAs($this->teacher);

    visit('/activities/create')
        ->select('@wizard-course-space', $this->space->id)
        ->fill('@wizard-title', 'Sorting quiz')
        ->fill('@wizard-description', 'Bubble sort and quicksort')
        ->click('@wizard-next')
        ->assertSee('This activity type is not available yet')
        ->assertNoJavaScriptErrors();

    $activity = Activity::query()->sole();
    expect($activity->title)->toBe('Sorting quiz')
        ->and($activity->status)->toBe(ActivityStatus::Draft);

    visit("/activities/{$activity->id}/edit?step=basics")
        ->assertValue('@wizard-title', 'Sorting quiz')
        ->assertValue('@wizard-description', 'Bubble sort and quicksort')
        ->assertNoJavaScriptErrors();

    $this->actingAs($this->student);

    visit('/activities')
        ->assertSee('There are no activities in your course spaces yet.')
        ->assertDontSee('Sorting quiz')
        ->assertNoJavaScriptErrors();
});

it('shows a title error from the autosave and stores nothing', function () {
    $this->actingAs($this->teacher);

    visit('/activities/create')
        ->select('@wizard-course-space', $this->space->id)
        ->click('@wizard-next')
        ->assertSee('Your changes could not be saved.')
        ->assertNoJavaScriptErrors();

    expect(Activity::query()->count())->toBe(0);
});

<?php

use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Enums\EnrolmentPolicy;
use App\Modules\Courses\Exceptions\InvalidCourseSpaceTransition;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-021 — Manage course space; ENT-CourseSpace state model; tenant isolation
| of ENT-CourseSpace and ENT-Enrolment.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->beta = $this->createOrganization(['subdomain' => 'beta']);

    $this->actingAsTenant($this->acme);
    $this->teacher = User::factory()->teacher()->create();
});

it('isolates course spaces per organisation', function () {
    $this->assertTenantIsolated(CourseSpace::class, fn () => CourseSpace::factory()->create());
});

it('isolates enrolments per organisation', function () {
    $this->assertTenantIsolated(Enrolment::class, fn () => Enrolment::factory()->create());
});

it('lets a teacher create a course space they own, visible only within their organisation', function () {
    $this->actingAs($this->teacher)
        ->post($this->tenantUrl($this->acme, 'courses'), [
            'title' => 'Algorithms',
            'description' => 'Graphs and trees',
            'enrolment_policy' => 'key',
            'enrolment_key' => 'K7P2Q',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $space = CourseSpace::query()->sole();

    expect($space->teacher_id)->toBe($this->teacher->id)
        ->and($space->organization_id)->toBe($this->acme->id)
        ->and($space->status)->toBe(CourseSpaceStatus::Active)
        ->and($space->enrolment_policy)->toBe(EnrolmentPolicy::Key)
        ->and($space->enrolment_key)->toBe('K7P2Q');

    // The owner sees it in their list.
    $this->actingAs($this->teacher)
        ->get($this->tenantUrl($this->acme, 'courses'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Index')
            ->has('spaces', 1)
            ->where('spaces.0.id', $space->id));

    // Another organisation neither lists nor opens it.
    $this->actingAsTenant($this->beta);
    $betaStudent = User::factory()->student()->create();
    $betaAdmin = User::factory()->orgAdmin()->create();

    expect(CourseSpace::query()->count())->toBe(0);

    $this->actingAs($betaStudent)
        ->get($this->tenantUrl($this->beta, 'catalogue'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('spaces.data', 0));

    $this->actingAs($betaAdmin)
        ->get($this->tenantUrl($this->beta, 'courses/'.$space->id))
        ->assertNotFound();

    $this->actingAs($betaAdmin)
        ->get($this->tenantUrl($this->beta, 'courses'))
        ->assertInertia(fn (Assert $page) => $page->has('spaces', 0));
});

it('ignores an owner or organisation given in the create form', function () {
    $otherTeacher = User::factory()->teacher()->create();

    $this->actingAs($this->teacher)
        ->post($this->tenantUrl($this->acme, 'courses'), [
            'title' => 'Algorithms',
            'enrolment_policy' => 'open',
            'teacher_id' => $otherTeacher->id,
            'organization_id' => $this->beta->id,
            'status' => 'archived',
        ])
        ->assertSessionHasNoErrors();

    $space = CourseSpace::query()->sole();

    expect($space->teacher_id)->toBe($this->teacher->id)
        ->and($space->organization_id)->toBe($this->acme->id)
        ->and($space->status)->toBe(CourseSpaceStatus::Active);
});

it('requires an enrolment key when the policy is key', function () {
    $this->actingAs($this->teacher)
        ->post($this->tenantUrl($this->acme, 'courses'), [
            'title' => 'Algorithms',
            'enrolment_policy' => 'key',
            'enrolment_key' => '',
        ])
        ->assertSessionHasErrors('enrolment_key');

    $this->actingAs($this->teacher)
        ->post($this->tenantUrl($this->acme, 'courses'), [
            'title' => '',
            'enrolment_policy' => 'invite-only',
        ])
        ->assertSessionHasErrors(['title', 'enrolment_policy']);

    expect(CourseSpace::query()->count())->toBe(0);
});

it('lets the owning teacher edit the space and drops the key of an open space', function () {
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->withKey('K7P2Q')->create();

    $this->actingAs($this->teacher)
        ->get($this->tenantUrl($this->acme, "courses/{$space->id}/edit"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Edit')
            ->where('space.enrolment_key', 'K7P2Q'));

    $this->actingAs($this->teacher)
        ->put($this->tenantUrl($this->acme, "courses/{$space->id}"), [
            'title' => 'Algorithms II',
            'description' => null,
            'enrolment_policy' => 'open',
            'enrolment_key' => 'K7P2Q',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect($this->tenantUrl($this->acme, "courses/{$space->id}"));

    $space->refresh();

    expect($space->title)->toBe('Algorithms II')
        ->and($space->description)->toBeNull()
        ->and($space->enrolment_policy)->toBe(EnrolmentPolicy::Open)
        ->and($space->enrolment_key)->toBeNull();
});

it('lets the owning teacher archive a space and make it active again', function () {
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();

    $this->actingAs($this->teacher)
        ->post($this->tenantUrl($this->acme, "courses/{$space->id}/archive"))
        ->assertSessionHasNoErrors()
        ->assertRedirect($this->tenantUrl($this->acme, "courses/{$space->id}"));

    expect($space->refresh()->status)->toBe(CourseSpaceStatus::Archived);

    $this->actingAs($this->teacher)
        ->post($this->tenantUrl($this->acme, "courses/{$space->id}/restore"))
        ->assertSessionHasNoErrors();

    expect($space->refresh()->status)->toBe(CourseSpaceStatus::Active);
});

it('rejects a transition outside the state model and keeps the state', function (CourseSpaceStatus $state) {
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')
        ->when($state === CourseSpaceStatus::Archived, fn ($factory) => $factory->archived())
        ->create();

    // The only transition the state model does not list from each state is
    // to the same state.
    expect(fn () => $space->transitionTo($state))->toThrow(InvalidCourseSpaceTransition::class)
        ->and($space->status)->toBe($state)
        ->and($space->refresh()->status)->toBe($state);

    $space->status = $state;
    $space->save(); // not dirty: nothing happens
    expect($space->refresh()->status)->toBe($state);

    // Over HTTP: archiving an archived space, restoring an active one.
    $path = $state === CourseSpaceStatus::Archived ? 'archive' : 'restore';
    $this->actingAs($this->teacher)
        ->post($this->tenantUrl($this->acme, "courses/{$space->id}/{$path}"))
        ->assertSessionHasErrors('status');

    expect($space->refresh()->status)->toBe($state);
})->with([
    'active' => CourseSpaceStatus::Active,
    'archived' => CourseSpaceStatus::Archived,
]);

it('rejects a status the state model does not know or a forced status change', function () {
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();

    // A space must start active.
    expect(fn () => CourseSpace::factory()->for($this->teacher, 'teacher')->create(['status' => CourseSpaceStatus::Archived]))
        ->toThrow(InvalidCourseSpaceTransition::class);

    // The database refuses unknown states.
    expect(fn () => DB::transaction(fn () => CourseSpace::query()->whereKey($space->id)->update(['status' => 'deleted'])))
        ->toThrow(QueryException::class);

    expect($space->refresh()->status)->toBe(CourseSpaceStatus::Active)
        ->and(CourseSpace::query()->count())->toBe(1);
});

it('keeps one enrolment per student and course space in the database', function () {
    $enrolment = Enrolment::factory()->create();

    expect(fn () => DB::transaction(fn () => Enrolment::query()->create([
        'course_space_id' => $enrolment->course_space_id,
        'student_id' => $enrolment->student_id,
        'enrolled_at' => now(),
    ])))->toThrow(QueryException::class);

    expect(Enrolment::query()->count())->toBe(1);
});

it('shows the role\'s course spaces: own for a teacher, all for an org admin, enrolled for a student', function () {
    $other = User::factory()->teacher()->create();
    $own = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'A own']);
    $foreign = CourseSpace::factory()->for($other, 'teacher')->create(['title' => 'B foreign']);
    $student = User::factory()->student()->create();
    Enrolment::factory()->for($foreign, 'courseSpace')->for($student, 'student')->create();
    $admin = User::factory()->orgAdmin()->create();

    $this->actingAs($this->teacher)->get($this->tenantUrl($this->acme, 'courses'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('spaces', 1)->where('spaces.0.id', $own->id)
            ->where('canCreate', true)->where('canBrowseCatalogue', false));

    $this->actingAs($admin)->get($this->tenantUrl($this->acme, 'courses'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('spaces', 2)
            ->where('spaces.1.enrolments_count', 1)
            ->where('canCreate', false));

    $this->actingAs($student)->get($this->tenantUrl($this->acme, 'courses'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('spaces', 1)->where('spaces.0.id', $foreign->id)
            ->where('spaces.0.enrolments_count', null)
            ->where('canBrowseCatalogue', true));
});

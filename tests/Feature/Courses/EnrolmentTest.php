<?php

use App\Modules\Courses\CoursesServiceProvider;
use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-011 — Browse catalogue and enrol; UC-021 — teacher adds and removes
| students.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->beta = $this->createOrganization(['subdomain' => 'beta']);

    $this->actingAsTenant($this->acme);
    $this->teacher = User::factory()->teacher()->create(['name' => 'Ada Teacher']);
    $this->student = User::factory()->student()->create();
});

function courseEnrolUrl(CourseSpace $space): string
{
    return test()->tenantUrl(test()->acme, "courses/{$space->id}/enrolment");
}

function courseEnrolmentsOf(User $student): int
{
    return Enrolment::query()->where('student_id', $student->id)->count();
}

it('enrols a student in an open space without a key', function () {
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();

    $this->actingAs($this->student)
        ->post(courseEnrolUrl($space))
        ->assertSessionHasNoErrors()
        ->assertRedirect($this->tenantUrl($this->acme, "courses/{$space->id}"));

    $enrolment = Enrolment::query()->sole();

    expect($enrolment->course_space_id)->toBe($space->id)
        ->and($enrolment->student_id)->toBe($this->student->id)
        ->and($enrolment->organization_id)->toBe($this->acme->id)
        ->and($enrolment->enrolled_at)->not->toBeNull();

    // The space appears among the student's enrolled spaces.
    $this->actingAs($this->student)->get($this->tenantUrl($this->acme, 'courses'))
        ->assertInertia(fn (Assert $page) => $page->has('spaces', 1)->where('spaces.0.id', $space->id));
});

it('enrols a student with the right key and refuses a wrong one', function () {
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->withKey('K7P2Q')->create();
    $other = User::factory()->student()->create();

    $this->actingAs($other)
        ->post(courseEnrolUrl($space), ['enrolment_key' => 'WRONG'])
        ->assertSessionHasErrors('enrolment_key');

    $this->actingAs($other)
        ->post(courseEnrolUrl($space))
        ->assertSessionHasErrors('enrolment_key');

    expect(courseEnrolmentsOf($other))->toBe(0);

    $this->actingAs($this->student)
        ->post(courseEnrolUrl($space), ['enrolment_key' => 'K7P2Q'])
        ->assertSessionHasNoErrors();

    expect(courseEnrolmentsOf($this->student))->toBe(1);
});

it('keeps exactly one enrolment when an enrolled student enrols again', function () {
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->withKey('K7P2Q')->create();

    $this->actingAs($this->student)->post(courseEnrolUrl($space), ['enrolment_key' => 'K7P2Q'])->assertSessionHasNoErrors();
    $this->actingAs($this->student)->post(courseEnrolUrl($space), ['enrolment_key' => 'K7P2Q'])->assertSessionHasNoErrors();
    $this->actingAs($this->student)->post(courseEnrolUrl($space))->assertSessionHasNoErrors();

    expect(Enrolment::query()->where('course_space_id', $space->id)->where('student_id', $this->student->id)->count())->toBe(1);
});

it('refuses enrolment in an archived space', function () {
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->archived()->create();

    // DATA-012: an archived space does not exist for students.
    $this->actingAs($this->student)->post(courseEnrolUrl($space))->assertNotFound();

    expect(courseEnrolmentsOf($this->student))->toBe(0);
});

it('lets only students enrol', function () {
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
    $admin = User::factory()->orgAdmin()->create();

    $this->actingAs($this->teacher)->post(courseEnrolUrl($space))->assertForbidden();
    $this->actingAs($admin)->post(courseEnrolUrl($space))->assertForbidden();

    expect(Enrolment::query()->count())->toBe(0);
});

it('does not let a student enrol in a space of another organisation', function () {
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();

    $this->actingAsTenant($this->beta);
    $betaStudent = User::factory()->student()->create();

    $this->actingAs($betaStudent)
        ->post($this->tenantUrl($this->beta, "courses/{$space->id}/enrolment"))
        ->assertNotFound();

    // Not on acme's subdomain either: the user belongs to beta.
    $this->actingAs($betaStudent)->post(courseEnrolUrl($space));

    $this->actingAsTenant($this->acme);
    expect(Enrolment::query()->count())->toBe(0);
});

it('limits enrolment key attempts to 10 per minute per user', function () {
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->withKey('K7P2Q')->create();

    for ($i = 0; $i < CoursesServiceProvider::ENROLMENT_ATTEMPTS_PER_MINUTE; $i++) {
        $this->actingAs($this->student)
            ->post(courseEnrolUrl($space), ['enrolment_key' => "WRONG{$i}"])
            ->assertSessionHasErrors('enrolment_key');
    }

    $this->actingAs($this->student)
        ->post(courseEnrolUrl($space), ['enrolment_key' => 'K7P2Q'])
        ->assertTooManyRequests();

    expect(courseEnrolmentsOf($this->student))->toBe(0);

    // Another user has their own limit.
    $other = User::factory()->student()->create();
    $this->actingAs($other)
        ->post(courseEnrolUrl($space), ['enrolment_key' => 'K7P2Q'])
        ->assertSessionHasNoErrors();

    expect(courseEnrolmentsOf($other))->toBe(1);
});

it('lists only active course spaces of the student\'s organisation in the catalogue', function () {
    $active = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);
    CourseSpace::factory()->for($this->teacher, 'teacher')->archived()->create(['title' => 'Old course']);

    $this->actingAsTenant($this->beta);
    $betaTeacher = User::factory()->teacher()->create();
    CourseSpace::factory()->for($betaTeacher, 'teacher')->create(['title' => 'Beta course']);

    $this->actingAs($this->student)
        ->get($this->tenantUrl($this->acme, 'catalogue'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalogue')
            ->has('spaces.data', 1)
            ->where('spaces.data.0.id', $active->id)
            ->where('spaces.data.0.enrolled', false)
            ->missing('spaces.data.0.enrolment_key')
            ->has('teachers', 1)
            ->where('teachers.0.id', $this->teacher->id));
});

it('searches the catalogue by title or description and filters it by teacher', function () {
    $other = User::factory()->teacher()->create();
    $graphs = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms', 'description' => 'Graphs']);
    $physics = CourseSpace::factory()->for($other, 'teacher')->create(['title' => 'Physics', 'description' => 'Mechanics and 100% fun']);

    $get = fn (array $query) => $this->actingAs($this->student)
        ->get($this->tenantUrl($this->acme, 'catalogue?'.http_build_query($query)));

    $get(['search' => 'graph'])->assertInertia(fn (Assert $page) => $page
        ->has('spaces.data', 1)->where('spaces.data.0.id', $graphs->id)
        ->where('filters.search', 'graph'));

    $get(['search' => '100%'])->assertInertia(fn (Assert $page) => $page
        ->has('spaces.data', 1)->where('spaces.data.0.id', $physics->id));

    $get(['search' => '%'])->assertInertia(fn (Assert $page) => $page->has('spaces.data', 1));

    $get(['teacher' => $other->id])->assertInertia(fn (Assert $page) => $page
        ->has('spaces.data', 1)->where('spaces.data.0.id', $physics->id));

    $get(['teacher' => 'not-a-uuid'])->assertSessionHasErrors('teacher');
});

it('keeps the catalogue for students', function () {
    $this->actingAs($this->teacher)->get($this->tenantUrl($this->acme, 'catalogue'))->assertForbidden();
    $this->actingAs(User::factory()->orgAdmin()->create())->get($this->tenantUrl($this->acme, 'catalogue'))->assertForbidden();
});

it('lets the owning teacher add and remove students', function () {
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->withKey('K7P2Q')->create();

    $this->actingAs($this->teacher)
        ->post($this->tenantUrl($this->acme, "courses/{$space->id}/students"), ['student_id' => $this->student->id])
        ->assertSessionHasNoErrors();

    $enrolment = Enrolment::query()->sole();
    expect($enrolment->student_id)->toBe($this->student->id);

    $this->actingAs($this->teacher)
        ->get($this->tenantUrl($this->acme, "courses/{$space->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageEnrolments', true)
            ->where('space.enrolment_key', 'K7P2Q')
            ->has('students', 1)
            ->where('students.0.enrolment_id', $enrolment->id)
            ->has('availableStudents', 0));

    $this->actingAs($this->teacher)
        ->delete($this->tenantUrl($this->acme, "courses/{$space->id}/students/{$enrolment->id}"))
        ->assertSessionHasNoErrors();

    expect(Enrolment::query()->count())->toBe(0);
});

it('adds only active students of the organisation to an active space', function () {
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
    $otherTeacher = User::factory()->teacher()->create();
    $inactive = User::factory()->student()->inactive()->create();

    $this->actingAsTenant($this->beta);
    $betaStudent = User::factory()->student()->create();
    $this->actingAsTenant($this->acme);

    foreach ([$otherTeacher->id, $inactive->id, $betaStudent->id] as $id) {
        $this->actingAs($this->teacher)
            ->post($this->tenantUrl($this->acme, "courses/{$space->id}/students"), ['student_id' => $id])
            ->assertSessionHasErrors('student_id');
    }

    $space->transitionTo(CourseSpaceStatus::Archived);
    $this->actingAs($this->teacher)
        ->post($this->tenantUrl($this->acme, "courses/{$space->id}/students"), ['student_id' => $this->student->id])
        ->assertSessionHasErrors('student_id');

    expect(Enrolment::query()->count())->toBe(0);
});

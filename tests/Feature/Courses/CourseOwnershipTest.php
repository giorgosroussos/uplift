<?php

use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Enums\EnrolmentPolicy;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

/*
| OWN-003 (ENT-Enrolment), OWN-004 (ENT-CourseSpace), SCOPE-007 and the
| UC-021 acceptance criteria on archived spaces, other teachers' spaces and
| org admins.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacherA = User::factory()->teacher()->create();
    $this->teacherB = User::factory()->teacher()->create();
    $this->admin = User::factory()->orgAdmin()->create();
    $this->student = User::factory()->student()->create();
    $this->otherStudent = User::factory()->student()->create();

    $this->space = CourseSpace::factory()->for($this->teacherA, 'teacher')->withKey('K7P2Q')
        ->create(['title' => 'Algorithms', 'description' => 'Graphs']);
    $this->enrolment = Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();
});

/**
 * @return array<string, mixed>
 */
function courseSpaceSnapshot(CourseSpace $space): array
{
    return $space->fresh()?->only(['title', 'description', 'enrolment_policy', 'enrolment_key', 'status', 'teacher_id']) ?? [];
}

function courseSpaceUrl(CourseSpace $space, string $path = ''): string
{
    return test()->tenantUrl(test()->acme, rtrim("courses/{$space->id}/{$path}", '/'));
}

/**
 * Every write a non-owner might attempt on a course space, its activities
 * or its enrolments; each must be refused and change nothing.
 */
function attemptCourseSpaceWrites(User $actor, CourseSpace $space, Enrolment $enrolment): void
{
    $valid = ['title' => 'Hijacked', 'description' => 'x', 'enrolment_policy' => 'open'];

    test()->actingAs($actor)->get(courseSpaceUrl($space, 'edit'))->assertForbidden();
    test()->actingAs($actor)->put(courseSpaceUrl($space), $valid)->assertForbidden();
    test()->actingAs($actor)->post(courseSpaceUrl($space, 'archive'))->assertForbidden();
    test()->actingAs($actor)->post(courseSpaceUrl($space, 'students'), ['student_id' => test()->otherStudent->id])->assertForbidden();
    test()->actingAs($actor)->delete(courseSpaceUrl($space, "students/{$enrolment->id}"))->assertForbidden();
}

describe('OWN-004 / UC-021: another teacher', function () {
    it('cannot edit, archive or restore a space of teacher A, nor manage its students or activities', function () {
        $before = courseSpaceSnapshot($this->space);

        attemptCourseSpaceWrites($this->teacherB, $this->space, $this->enrolment);

        $this->space->transitionTo(CourseSpaceStatus::Archived);
        $this->actingAs($this->teacherB)->post(courseSpaceUrl($this->space, 'restore'))->assertForbidden();

        expect(courseSpaceSnapshot($this->space))->toBe([...$before, 'status' => CourseSpaceStatus::Archived])
            ->and(Enrolment::query()->pluck('id')->all())->toBe([$this->enrolment->id])
            ->and(Gate::forUser($this->teacherB)->allows('update', $this->space))->toBeFalse()
            ->and(Gate::forUser($this->teacherB)->allows('manageActivities', $this->space))->toBeFalse()
            ->and(Gate::forUser($this->teacherA)->allows('manageActivities', $this->space))->toBeTrue();
    });

    it('cannot open a space of teacher A, which would show its students and key', function () {
        $this->actingAs($this->teacherB)->get(courseSpaceUrl($this->space))->assertForbidden();
    });
});

describe('OWN-004 / UC-021: org admin', function () {
    it('can view any course space of the organisation', function () {
        $this->actingAs($this->admin)
            ->get(courseSpaceUrl($this->space))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Courses/Show')
                ->where('space.id', $this->space->id)
                ->where('space.title', 'Algorithms')
                ->where('enrolmentsCount', 1)
                ->where('can.update', false)
                ->where('can.manageEnrolments', false));
    });

    it('cannot edit, archive, or manage students of a course space', function () {
        $before = courseSpaceSnapshot($this->space);

        attemptCourseSpaceWrites($this->admin, $this->space, $this->enrolment);
        $this->actingAs($this->admin)->post(courseSpaceUrl($this->space, 'restore'))->assertForbidden();

        expect(courseSpaceSnapshot($this->space))->toBe($before)
            ->and(Enrolment::query()->count())->toBe(1)
            ->and(Gate::forUser($this->admin)->allows('manageActivities', $this->space))->toBeFalse();
    });

    it('is not shown the enrolment key or the individual students', function () {
        $this->actingAs($this->admin)
            ->get(courseSpaceUrl($this->space))
            ->assertInertia(fn (Assert $page) => $page
                ->where('space.enrolment_key', null)
                ->where('students', [])
                ->where('availableStudents', []));

        expect(Gate::forUser($this->admin)->allows('view', $this->enrolment))->toBeFalse()
            ->and(Gate::forUser($this->admin)->allows('delete', $this->enrolment))->toBeFalse();
    });

    it('cannot create course spaces', function () {
        $this->actingAs($this->admin)->get($this->tenantUrl($this->acme, 'courses/create'))->assertForbidden();
        $this->actingAs($this->admin)
            ->post($this->tenantUrl($this->acme, 'courses'), ['title' => 'X', 'enrolment_policy' => 'open'])
            ->assertForbidden();

        expect(CourseSpace::query()->count())->toBe(1);
    });

    it('cannot view a course space of another organisation', function () {
        $beta = $this->createOrganization(['subdomain' => 'beta']);
        $betaSpace = $this->asTenant($beta, fn () => CourseSpace::factory()->create());

        $this->actingAs($this->admin)
            ->get($this->tenantUrl($this->acme, "courses/{$betaSpace->id}"))
            ->assertNotFound();

        expect(Gate::forUser($this->admin)->allows('view', $betaSpace))->toBeFalse();
    });
});

describe('OWN-003: enrolments', function () {
    it('lets a student see only their own enrolments', function () {
        $otherSpace = CourseSpace::factory()->for($this->teacherB, 'teacher')->create();
        $foreign = Enrolment::factory()->for($otherSpace, 'courseSpace')->for($this->otherStudent, 'student')->create();

        expect(Gate::forUser($this->student)->allows('view', $this->enrolment))->toBeTrue()
            ->and(Gate::forUser($this->student)->allows('view', $foreign))->toBeFalse()
            ->and(Gate::forUser($this->student)->allows('update', $this->enrolment))->toBeFalse()
            ->and(Gate::forUser($this->student)->allows('delete', $this->enrolment))->toBeFalse();

        // The list shows only spaces the student is enrolled in.
        $this->actingAs($this->student)->get($this->tenantUrl($this->acme, 'courses'))
            ->assertInertia(fn (Assert $page) => $page->has('spaces', 1)->where('spaces.0.id', $this->space->id));

        // The space page lists no students and no key.
        $this->actingAs($this->student)->get(courseSpaceUrl($this->space))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('enrolled', true)
                ->where('students', [])
                ->where('enrolmentsCount', null)
                ->where('space.enrolment_key', null));
    });

    it('does not let a student remove or add enrolments', function () {
        $this->actingAs($this->otherStudent)
            ->delete(courseSpaceUrl($this->space, "students/{$this->enrolment->id}"))
            ->assertForbidden();
        $this->actingAs($this->student)
            ->delete(courseSpaceUrl($this->space, "students/{$this->enrolment->id}"))
            ->assertForbidden();
        $this->actingAs($this->student)
            ->post(courseSpaceUrl($this->space, 'students'), ['student_id' => $this->otherStudent->id])
            ->assertForbidden();

        expect(Enrolment::query()->pluck('id')->all())->toBe([$this->enrolment->id]);
    });

    it('lets a teacher see enrolments only of course spaces they own', function () {
        expect(Gate::forUser($this->teacherA)->allows('view', $this->enrolment))->toBeTrue()
            ->and(Gate::forUser($this->teacherB)->allows('view', $this->enrolment))->toBeFalse()
            ->and(Gate::forUser($this->teacherB)->allows('delete', $this->enrolment))->toBeFalse()
            ->and(Gate::forUser($this->teacherA)->allows('update', $this->enrolment))->toBeFalse();
    });

    it('does not let a teacher remove an enrolment of another teacher\'s space through their own space', function () {
        $ownSpace = CourseSpace::factory()->for($this->teacherB, 'teacher')->create();

        // The enrolment is bound only within the space of the URL.
        $this->actingAs($this->teacherB)
            ->delete($this->tenantUrl($this->acme, "courses/{$ownSpace->id}/students/{$this->enrolment->id}"))
            ->assertNotFound();

        expect(Enrolment::query()->pluck('id')->all())->toBe([$this->enrolment->id]);
    });
});

describe('UC-021: archived course space', function () {
    it('is hidden from students, even one whose enrolment was kept, and refuses their activities (DATA-012)', function () {
        expect(Gate::forUser($this->student)->allows('participate', $this->space))->toBeTrue();

        // Archived without the action, so the enrolment row is still there.
        $this->space->transitionTo(CourseSpaceStatus::Archived);

        $gate = Gate::forUser($this->student);
        expect($gate->allows('view', $this->space))->toBeFalse()
            ->and($gate->allows('participate', $this->space))->toBeFalse()
            ->and($gate->allows('enrol', $this->space))->toBeFalse();

        $this->actingAs($this->student)->get(courseSpaceUrl($this->space))->assertNotFound();
        $this->actingAs($this->student)->get($this->tenantUrl($this->acme, 'courses'))
            ->assertInertia(fn (Assert $page) => $page->has('spaces', 0));
    });

    it('is hidden from students who are not enrolled', function () {
        $this->space->transitionTo(CourseSpaceStatus::Archived);

        $this->actingAs($this->otherStudent)->get(courseSpaceUrl($this->space))->assertNotFound();
        expect(Gate::forUser($this->otherStudent)->allows('participate', $this->space))->toBeFalse();
    });

    it('lets only enrolled students submit to an active space', function () {
        expect(Gate::forUser($this->otherStudent)->allows('participate', $this->space))->toBeFalse()
            ->and(Gate::forUser($this->teacherA)->allows('participate', $this->space))->toBeFalse();
    });
});

it('shows a student the enrol form of an active space without its key', function () {
    $this->actingAs($this->otherStudent)->get(courseSpaceUrl($this->space))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrolled', false)
            ->where('can.enrol', true)
            ->where('space.enrolment_policy', EnrolmentPolicy::Key->value)
            ->where('space.enrolment_key', null));
});

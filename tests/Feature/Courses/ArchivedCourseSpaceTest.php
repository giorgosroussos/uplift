<?php

use App\Modules\Courses\Actions\AddStudentToCourseSpace;
use App\Modules\Courses\Actions\ChangeCourseSpaceStatus;
use App\Modules\Courses\Actions\EnrolInCourseSpace;
use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Events\CourseSpaceArchived;
use App\Modules\Courses\Models\Announcement;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Courses\Policies\CourseSpacePolicy;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/*
| DATA-012 / UC-021: archiving a course space deletes all its enrolments in
| the same transaction and dispatches CourseSpaceArchived after commit; an
| archived space is hidden from students (404) and seen only by its owning
| teacher and org admins; restoring gives an active space without students.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->admin = User::factory()->orgAdmin()->create();
    $this->students = User::factory()->student()->count(3)->create();
    $this->student = $this->students->first();

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);
    foreach ($this->students as $student) {
        Enrolment::factory()->for($this->space, 'courseSpace')->for($student, 'student')->create();
    }

    // Another space of the same teacher keeps its enrolments.
    $this->otherSpace = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Databases']);
    $this->otherEnrolment = Enrolment::factory()->for($this->otherSpace, 'courseSpace')->for($this->student, 'student')->create();
});

function archivedSpaceUrl(CourseSpace $space, string $path = ''): string
{
    return test()->tenantUrl(test()->acme, rtrim("courses/{$space->id}/{$path}", '/'));
}

function archiveAsOwner(CourseSpace $space): void
{
    test()->actingAs(test()->teacher)
        ->post(archivedSpaceUrl($space, 'archive'))
        ->assertSessionHasNoErrors()
        ->assertRedirect(archivedSpaceUrl($space));
}

describe('archiving', function () {
    it('deletes every enrolment of the space and dispatches one CourseSpaceArchived with removedEnrolments 3', function () {
        Event::fake([CourseSpaceArchived::class]);

        archiveAsOwner($this->space);

        expect($this->space->refresh()->status)->toBe(CourseSpaceStatus::Archived)
            ->and($this->space->enrolments()->count())->toBe(0)
            ->and(Enrolment::query()->pluck('id')->all())->toBe([$this->otherEnrolment->id]);

        Event::assertDispatchedTimes(CourseSpaceArchived::class, 1);
        Event::assertDispatched(CourseSpaceArchived::class, fn (CourseSpaceArchived $event): bool => $event->organizationId === $this->acme->id
            && $event->courseSpaceId === $this->space->id
            && $event->actorId === $this->teacher->id
            && $event->removedEnrolments === 3);
    });

    it('dispatches CourseSpaceArchived only after commit and keeps the enrolments when the transaction rolls back', function () {
        $seen = [];
        Event::listen(CourseSpaceArchived::class, function (CourseSpaceArchived $event) use (&$seen): void {
            $seen[] = $event->removedEnrolments;
        });

        try {
            DB::transaction(function () {
                app(ChangeCourseSpaceStatus::class)->handle($this->teacher, $this->space, CourseSpaceStatus::Archived);

                throw new RuntimeException('abort');
            });
        } catch (RuntimeException) {
        }

        expect($seen)->toBe([])
            ->and($this->space->fresh()?->status)->toBe(CourseSpaceStatus::Active)
            ->and($this->space->enrolments()->count())->toBe(3);

        app(ChangeCourseSpaceStatus::class)->handle($this->teacher, $this->space, CourseSpaceStatus::Archived);

        expect($seen)->toBe([3])
            ->and($this->space->enrolments()->count())->toBe(0);
    });

    it('deletes nothing and dispatches nothing when the transition is refused', function () {
        Event::fake([CourseSpaceArchived::class]);
        $this->space->transitionTo(CourseSpaceStatus::Archived); // bypasses the action: enrolments stay

        $this->actingAs($this->teacher)
            ->post(archivedSpaceUrl($this->space, 'archive'))
            ->assertSessionHasErrors('status');

        expect($this->space->refresh()->status)->toBe(CourseSpaceStatus::Archived)
            ->and($this->space->enrolments()->count())->toBe(3);
        Event::assertNotDispatched(CourseSpaceArchived::class);
    });

    it('is refused to other teachers, org admins and students and changes nothing', function () {
        Event::fake([CourseSpaceArchived::class]);
        $otherTeacher = User::factory()->teacher()->create();

        $this->actingAs($otherTeacher)->post(archivedSpaceUrl($this->space, 'archive'))->assertForbidden();
        $this->actingAs($this->admin)->post(archivedSpaceUrl($this->space, 'archive'))->assertForbidden();
        $this->actingAs($this->student)->post(archivedSpaceUrl($this->space, 'archive'))->assertForbidden();

        expect($this->space->refresh()->status)->toBe(CourseSpaceStatus::Active)
            ->and($this->space->enrolments()->count())->toBe(3);
        Event::assertNotDispatched(CourseSpaceArchived::class);
    });

    it('does not enrol a student into a space that is archived meanwhile', function () {
        $stale = CourseSpace::query()->findOrFail($this->otherSpace->id); // loaded while active
        app(ChangeCourseSpaceStatus::class)->handle($this->teacher, $this->otherSpace, CourseSpaceStatus::Archived);

        $newcomer = User::factory()->student()->create();

        expect(fn () => app(EnrolInCourseSpace::class)->handle($newcomer, $stale, null))
            ->toThrow(ValidationException::class)
            ->and(fn () => app(AddStudentToCourseSpace::class)->handle($stale, $newcomer->id))
            ->toThrow(ValidationException::class)
            ->and($this->otherSpace->enrolments()->count())->toBe(0);
    });
});

describe('hidden from students', function () {
    beforeEach(function () {
        Announcement::factory()->for($this->space, 'courseSpace')->create();
        archiveAsOwner($this->space);
    });

    it('is absent from a formerly enrolled student\'s list and from the catalogue', function () {
        $this->actingAs($this->student)
            ->get($this->tenantUrl($this->acme, 'courses'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('spaces', 1)
                ->where('spaces.0.id', $this->otherSpace->id));

        $this->actingAs($this->student)
            ->get($this->tenantUrl($this->acme, 'catalogue'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('spaces.data', 1)
                ->where('spaces.data.0.id', $this->otherSpace->id)
                ->has('teachers', 1));

        $this->actingAs($this->student)
            ->get($this->tenantUrl($this->acme, 'catalogue').'?search=Algorithms')
            ->assertInertia(fn (Assert $page) => $page->has('spaces.data', 0));
    });

    it('returns 404 when a formerly enrolled or any other student opens it', function () {
        $stranger = User::factory()->student()->create();

        $this->actingAs($this->student)->get(archivedSpaceUrl($this->space))->assertNotFound();
        $this->actingAs($stranger)->get(archivedSpaceUrl($this->space))->assertNotFound();
    });

    it('returns 404 for every student request about it, its announcements and its activities', function () {
        $announcement = Announcement::query()->sole();
        $before = $announcement->only(['title', 'body']);

        $this->actingAs($this->student)->post(archivedSpaceUrl($this->space, 'enrolment'))->assertNotFound();
        $this->actingAs($this->student)
            ->post(archivedSpaceUrl($this->space, 'announcements'), ['title' => 'x', 'body' => 'y'])
            ->assertNotFound();
        $this->actingAs($this->student)
            ->put(archivedSpaceUrl($this->space, "announcements/{$announcement->id}"), ['title' => 'x', 'body' => 'y'])
            ->assertNotFound();
        $this->actingAs($this->student)
            ->delete(archivedSpaceUrl($this->space, "announcements/{$announcement->id}"))
            ->assertNotFound();

        $this->space->refresh();
        $gate = Gate::forUser($this->student);
        foreach (['view', 'enrol', 'participate', 'viewAnnouncements', 'manageActivities'] as $ability) {
            $response = $gate->inspect($ability, $this->space);
            expect($response->denied())->toBeTrue()
                ->and($response->status())->toBe(404);
        }
        expect($gate->inspect('view', $announcement)->status())->toBe(404)
            ->and(Announcement::query()->count())->toBe(1)
            ->and($announcement->fresh()?->only(['title', 'body']))->toBe($before)
            ->and($this->space->enrolments()->count())->toBe(0);
    });

    it('no longer offers the viewResults ability', function () {
        expect(method_exists(CourseSpacePolicy::class, 'viewResults'))->toBeFalse()
            ->and(Gate::forUser($this->student)->allows('viewResults', $this->space))->toBeFalse();
    });
});

describe('visible to its teacher and org admins', function () {
    beforeEach(function () {
        archiveAsOwner($this->space);
    });

    it('lets its owning teacher view it, in their list and on its page', function () {
        $this->actingAs($this->teacher)
            ->get($this->tenantUrl($this->acme, 'courses'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('spaces', 2)
                ->where('spaces.1.id', $this->space->id)
                ->where('spaces.1.status', 'archived')
                ->where('spaces.1.enrolments_count', 0));

        $this->actingAs($this->teacher)
            ->get(archivedSpaceUrl($this->space))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Courses/Show')
                ->where('space.status', 'archived')
                ->where('can.update', true)
                ->where('students', []));
    });

    it('lets an org admin of the organisation view it but not restore it', function () {
        $this->actingAs($this->admin)
            ->get(archivedSpaceUrl($this->space))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('space.status', 'archived')
                ->where('enrolmentsCount', 0)
                ->where('can.update', false));

        $this->actingAs($this->admin)->post(archivedSpaceUrl($this->space, 'restore'))->assertForbidden();
        expect($this->space->refresh()->status)->toBe(CourseSpaceStatus::Archived);
    });

    it('stays hidden from other teachers', function () {
        $otherTeacher = User::factory()->teacher()->create();

        $this->actingAs($otherTeacher)->get(archivedSpaceUrl($this->space))->assertForbidden();
    });
});

describe('restoring', function () {
    it('gives an active space with no enrolments, which students can enrol in again', function () {
        Event::fake([CourseSpaceArchived::class]);
        archiveAsOwner($this->space);

        $this->actingAs($this->teacher)
            ->post(archivedSpaceUrl($this->space, 'restore'))
            ->assertSessionHasNoErrors();

        expect($this->space->refresh()->status)->toBe(CourseSpaceStatus::Active)
            ->and($this->space->enrolments()->count())->toBe(0);
        Event::assertDispatchedTimes(CourseSpaceArchived::class, 1);

        // Visible again, but the former student is no longer enrolled.
        $this->actingAs($this->student)
            ->get(archivedSpaceUrl($this->space))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('enrolled', false)->where('can.enrol', true));

        $this->actingAs($this->student)->post(archivedSpaceUrl($this->space, 'enrolment'))->assertSessionHasNoErrors();
        expect($this->space->enrolments()->count())->toBe(1);
    });
});

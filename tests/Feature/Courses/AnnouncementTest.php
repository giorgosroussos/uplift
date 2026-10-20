<?php

use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Events\AnnouncementPublished;
use App\Modules\Courses\Models\Announcement;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-008: the owning teacher posts, edits and deletes announcements of a
| course space; the owning teacher and enrolled students read them.
| Ownership (SCOPE-007, OWN-004) and tenant isolation (SEC-004) of
| ENT-Announcement.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->beta = $this->createOrganization(['subdomain' => 'beta']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create(['name' => 'Maria Teacher']);
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->admin = User::factory()->orgAdmin()->create();
    $this->student = User::factory()->student()->create();
    $this->outsider = User::factory()->student()->create();

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();
});

function announcementsUrl(CourseSpace $space, string $path = ''): string
{
    return test()->tenantUrl(test()->acme, rtrim("courses/{$space->id}/announcements/{$path}", '/'));
}

/**
 * @return array<string, mixed>
 */
function announcementSnapshot(Announcement $announcement): array
{
    $fresh = Announcement::withTrashed()->find($announcement->id);

    return $fresh === null ? [] : [
        ...$fresh->only(['title', 'body', 'author_id', 'course_space_id']),
        'published_at' => $fresh->published_at?->toIso8601String(),
        'deleted_at' => $fresh->deleted_at?->toIso8601String(),
    ];
}

it('lets the owning teacher post an announcement that enrolled students read', function () {
    Event::fake([AnnouncementPublished::class]);

    $this->actingAs($this->teacher)
        ->post(announcementsUrl($this->space), ['title' => 'Exam room', 'body' => "Room A1\nBring ID"])
        ->assertRedirect($this->tenantUrl($this->acme, "courses/{$this->space->id}"));

    $announcement = Announcement::query()->sole();

    expect($announcement->organization_id)->toBe($this->acme->id)
        ->and($announcement->course_space_id)->toBe($this->space->id)
        ->and($announcement->author_id)->toBe($this->teacher->id)
        ->and($announcement->published_at)->not->toBeNull();

    Event::assertDispatchedTimes(AnnouncementPublished::class, 1);
    Event::assertDispatched(AnnouncementPublished::class, fn (AnnouncementPublished $event): bool => $event->announcementId === $announcement->id
        && $event->courseSpaceId === $this->space->id
        && $event->organizationId === $this->acme->id
        && $event->authorId === $this->teacher->id);

    foreach ([$this->teacher, $this->student] as $reader) {
        $this->actingAs($reader)
            ->get($this->tenantUrl($this->acme, "courses/{$this->space->id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Courses/Show')
                ->has('announcements', 1)
                ->where('announcements.0.id', $announcement->id)
                ->where('announcements.0.title', 'Exam room')
                ->where('announcements.0.body', "Room A1\nBring ID")
                ->where('announcements.0.author', 'Maria Teacher')
                ->where('can.manageAnnouncements', $reader->is($this->teacher)));
    }
});

it('lists announcements newest first', function () {
    Announcement::factory()->for($this->space, 'courseSpace')->create(['title' => 'Old', 'published_at' => now()->subDay()]);
    Announcement::factory()->for($this->space, 'courseSpace')->create(['title' => 'New', 'published_at' => now()]);

    $this->actingAs($this->student)
        ->get($this->tenantUrl($this->acme, "courses/{$this->space->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->where('announcements.0.title', 'New')
            ->where('announcements.1.title', 'Old'));
});

it('keeps announcements readable for enrolled students once the space is archived', function () {
    Announcement::factory()->for($this->space, 'courseSpace')->create();
    $this->space->transitionTo(CourseSpaceStatus::Archived);

    $this->actingAs($this->student)
        ->get($this->tenantUrl($this->acme, "courses/{$this->space->id}"))
        ->assertInertia(fn (Assert $page) => $page->has('announcements', 1));
});

it('rejects an announcement without title or body', function () {
    Event::fake([AnnouncementPublished::class]);

    $this->actingAs($this->teacher)
        ->post(announcementsUrl($this->space), ['title' => '', 'body' => ''])
        ->assertSessionHasErrors(['title', 'body']);

    $this->actingAs($this->teacher)
        ->post(announcementsUrl($this->space), ['title' => str_repeat('a', 256), 'body' => 'x'])
        ->assertSessionHasErrors(['title']);

    expect(Announcement::query()->count())->toBe(0);
    Event::assertNotDispatched(AnnouncementPublished::class);
});

it('lets the owning teacher edit an announcement without publishing it again', function () {
    Event::fake([AnnouncementPublished::class]);
    $announcement = Announcement::factory()->for($this->space, 'courseSpace')->create(['published_at' => now()->subHour()]);
    $publishedAt = $announcement->fresh()?->published_at;

    $this->actingAs($this->teacher)
        ->put(announcementsUrl($this->space, $announcement->id), ['title' => 'Fixed', 'body' => 'Corrected text'])
        ->assertRedirect();

    $fresh = $announcement->fresh();
    expect($fresh?->title)->toBe('Fixed')
        ->and($fresh?->body)->toBe('Corrected text')
        ->and($fresh?->published_at?->equalTo($publishedAt))->toBeTrue();
    Event::assertNotDispatched(AnnouncementPublished::class);
});

it('soft-deletes an announcement of the owning teacher, which then is no longer shown', function () {
    $announcement = Announcement::factory()->for($this->space, 'courseSpace')->create();

    $this->actingAs($this->teacher)
        ->delete(announcementsUrl($this->space, $announcement->id))
        ->assertRedirect();

    expect(Announcement::query()->count())->toBe(0)
        ->and(Announcement::withTrashed()->find($announcement->id)?->trashed())->toBeTrue();

    $this->actingAs($this->student)
        ->get($this->tenantUrl($this->acme, "courses/{$this->space->id}"))
        ->assertInertia(fn (Assert $page) => $page->has('announcements', 0));
});

describe('SCOPE-007 / OWN-004: only the owning teacher writes announcements', function () {
    it('refuses posting, editing and deleting to everyone else and changes nothing', function (string $actor) {
        Event::fake([AnnouncementPublished::class]);
        $announcement = Announcement::factory()->for($this->space, 'courseSpace')->create();
        $before = announcementSnapshot($announcement);
        $user = $this->{$actor};
        $valid = ['title' => 'Hijacked', 'body' => 'x'];

        $this->actingAs($user)->post(announcementsUrl($this->space), $valid)->assertForbidden();
        $this->actingAs($user)->put(announcementsUrl($this->space, $announcement->id), $valid)->assertForbidden();
        $this->actingAs($user)->delete(announcementsUrl($this->space, $announcement->id))->assertForbidden();

        expect(Announcement::withTrashed()->count())->toBe(1)
            ->and(announcementSnapshot($announcement))->toBe($before)
            ->and(Gate::forUser($user)->allows('manageAnnouncements', $this->space))->toBeFalse()
            ->and(Gate::forUser($user)->allows('update', $announcement))->toBeFalse()
            ->and(Gate::forUser($user)->allows('delete', $announcement))->toBeFalse();
        Event::assertNotDispatched(AnnouncementPublished::class);
    })->with(['otherTeacher', 'admin', 'student', 'outsider']);

    it('refuses editing an announcement through another course space of the same teacher', function () {
        $announcement = Announcement::factory()->for($this->space, 'courseSpace')->create();
        $before = announcementSnapshot($announcement);
        $mySecondSpace = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
        $foreignSpace = CourseSpace::factory()->for($this->otherTeacher, 'teacher')->create();
        $foreign = Announcement::factory()->for($foreignSpace, 'courseSpace')->create();
        $foreignBefore = announcementSnapshot($foreign);

        $this->actingAs($this->teacher)
            ->put(announcementsUrl($mySecondSpace, $announcement->id), ['title' => 'x', 'body' => 'y'])
            ->assertNotFound();
        $this->actingAs($this->teacher)
            ->delete(announcementsUrl($mySecondSpace, $foreign->id))
            ->assertNotFound();

        expect(announcementSnapshot($announcement))->toBe($before)
            ->and(announcementSnapshot($foreign))->toBe($foreignBefore);
    });
});

describe('UC-008: who reads announcements', function () {
    it('shows none to a student who is not enrolled, an org admin or another teacher', function () {
        Announcement::factory()->for($this->space, 'courseSpace')->create();
        $announcement = Announcement::query()->sole();

        foreach ([$this->outsider, $this->admin] as $user) {
            $this->actingAs($user)
                ->get($this->tenantUrl($this->acme, "courses/{$this->space->id}"))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('announcements', [])
                    ->where('can.manageAnnouncements', false));
        }

        $this->actingAs($this->otherTeacher)
            ->get($this->tenantUrl($this->acme, "courses/{$this->space->id}"))
            ->assertForbidden();

        foreach ([$this->outsider, $this->admin, $this->otherTeacher] as $user) {
            expect(Gate::forUser($user)->allows('view', $announcement))->toBeFalse()
                ->and(Gate::forUser($user)->allows('viewAnnouncements', $this->space))->toBeFalse();
        }

        expect(Gate::forUser($this->student)->allows('view', $announcement))->toBeTrue()
            ->and(Gate::forUser($this->teacher)->allows('view', $announcement))->toBeTrue();
    });
});

describe('SEC-004: tenant isolation', function () {
    it('never shows, edits or deletes an announcement of another organisation', function () {
        $announcement = Announcement::factory()->for($this->space, 'courseSpace')->create();
        $before = announcementSnapshot($announcement);

        $this->actingAsTenant($this->beta);
        $betaTeacher = User::factory()->teacher()->create();
        $betaSpace = CourseSpace::factory()->for($betaTeacher, 'teacher')->create();

        expect(Announcement::query()->count())->toBe(0);

        $base = "courses/{$betaSpace->id}/announcements/{$announcement->id}";
        $this->actingAs($betaTeacher)
            ->put($this->tenantUrl($this->beta, $base), ['title' => 'x', 'body' => 'y'])
            ->assertNotFound();
        $this->actingAs($betaTeacher)
            ->delete($this->tenantUrl($this->beta, $base))
            ->assertNotFound();
        $this->actingAs($betaTeacher)
            ->post($this->tenantUrl($this->beta, "courses/{$this->space->id}/announcements"), ['title' => 'x', 'body' => 'y'])
            ->assertNotFound();

        $this->actingAsTenant($this->acme);
        expect(announcementSnapshot($announcement))->toBe($before)
            ->and(Announcement::query()->count())->toBe(1);
    });
});

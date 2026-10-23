<?php

use App\Modules\Activities\Actions\DeleteActivity;
use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Events\ActivityDeleted;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Poll;
use App\Modules\Activities\Models\SlotActivity;
use App\Modules\Activities\Models\TopicActivity;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Audit\Enums\ActorType;
use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Enums\NotificationKind;
use App\Modules\Notifications\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-040 (SCOPE-029): the owning teacher deletes a draft, or a published or
| closed activity without submissions, after confirming it. Whether there
| are submissions is decided by each type (ActivityType::hasSubmissions):
| any quiz attempt (also in progress), poll participation, topic choice,
| topic proposal or current booking. The activity is soft-deleted, is 404
| from then on, notifies nobody and cannot be restored; ActivityDeleted
| follows after commit and is audited as activity.deleted.
|
| The OWN-008..OWN-011 and ENT-TopicProposal criteria of this phase are
| covered by TopicSelectionTest, TopicProposalTest, SlotBookingTest and
| PollTest.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create();
    $this->admin = User::factory()->orgAdmin()->create();

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();

    $this->deleted = [];
    Event::listen(ActivityDeleted::class, function (ActivityDeleted $event): void {
        $this->deleted[] = $event;
    });
});

function delUrl(string $path): string
{
    return test()->tenantUrl(test()->acme, ltrim($path, '/'));
}

/**
 * An activity of the course space with its subtype row, as its type's
 * module creates it.
 */
function delActivity(ActivityKind $kind, string $state = 'published'): Activity
{
    $factory = Activity::factory()->ofKind($kind)->for(test()->space, 'courseSpace');
    $activity = match ($state) {
        'published' => $factory->published()->create(),
        'closed' => $factory->closed()->create(),
        default => $factory->create(),
    };

    match ($kind) {
        ActivityKind::Quiz => Quiz::factory()->for($activity, 'activity')->withQuestions(1, Question::factory())->create(),
        ActivityKind::Poll => Poll::factory()->for($activity, 'activity')->withOptions(['Yes', 'No'])->create(),
        ActivityKind::TopicSelection => TopicActivity::factory()->for($activity, 'activity')->withTopics(['A' => 2])->create(),
        ActivityKind::TopicProposal => null,
        ActivityKind::SlotBooking => SlotActivity::factory()->for($activity, 'activity')->withSlots(['+1 day 10:00' => 2])->create(),
    };

    return $activity;
}

function deleteAs(User $user, Activity $activity): TestResponse
{
    return test()->actingAs($user)->delete(delUrl("activities/{$activity->id}"));
}

/**
 * The student submits to the activity through its own endpoint.
 */
function delSubmit(Activity $activity): void
{
    $test = test();
    $test->actingAs($test->student);

    $response = match ($activity->type) {
        ActivityKind::Quiz => null,
        ActivityKind::Poll => $test->post(delUrl("polls/{$activity->id}/vote"), [
            'options' => [Poll::forActivity($activity)?->options()->firstOrFail()->id],
        ]),
        ActivityKind::TopicSelection => $test->put(delUrl("topics/{$activity->id}/choice"), [
            'topic_id' => TopicActivity::forActivity($activity)?->topics()->firstOrFail()->id,
        ]),
        ActivityKind::TopicProposal => $test->post(delUrl("proposals/{$activity->id}"), ['title' => 'My topic']),
        ActivityKind::SlotBooking => $test->put(delUrl("slots/{$activity->id}/booking"), [
            'slot_id' => SlotActivity::forActivity($activity)?->slots()->firstOrFail()->id,
        ]),
    };

    if ($response === null) {
        QuizAttempt::factory()->create(['quiz_id' => $activity->id, 'student_id' => $test->student->id]);

        return;
    }

    $response->assertRedirect()->assertSessionHasNoErrors();
}

/**
 * @return list<string>
 */
function delListed(User $user): array
{
    $titles = [];

    test()->actingAs($user)
        ->get(delUrl('activities?course_space='.test()->space->id))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$titles): void {
            $titles = array_column($page->toArray()['props']['activities'], 'title');
        });

    return $titles;
}

describe('UC-040: Given a draft activity of each of the five types', function () {
    it('soft-deletes it when its owning teacher deletes it and dispatches one ActivityDeleted', function (ActivityKind $kind) {
        $activity = delActivity($kind, 'draft');

        deleteAs($this->teacher, $activity)
            ->assertRedirect(delUrl('activities?course_space='.$this->space->id))
            ->assertSessionHasNoErrors();

        expect(Activity::query()->find($activity->id))->toBeNull()
            ->and(Activity::withTrashed()->find($activity->id)?->trashed())->toBeTrue()
            ->and($this->deleted)->toHaveCount(1)
            ->and($this->deleted[0]->organizationId)->toBe($this->acme->id)
            ->and($this->deleted[0]->activityId)->toBe($activity->id)
            ->and($this->deleted[0]->actorId)->toBe($this->teacher->id)
            ->and($this->deleted[0]->type)->toBe($kind->value);
    })->with(fn () => ActivityKind::cases());

    it('dispatches ActivityDeleted only after commit, so a rolled back deletion dispatches nothing', function () {
        $activity = delActivity(ActivityKind::Poll, 'draft');

        try {
            DB::transaction(function () use ($activity): void {
                app(DeleteActivity::class)->handle($this->teacher, $activity);

                expect($this->deleted)->toBe([]);

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }

        expect($this->deleted)->toBe([])
            ->and(Activity::query()->find($activity->id))->not->toBeNull();
    });
});

describe('UC-040: Given an activity with a submission, deletion is refused', function () {
    it('refuses a published quiz with one in-progress attempt and changes nothing', function () {
        $activity = delActivity(ActivityKind::Quiz);
        delSubmit($activity);
        expect(QuizAttempt::query()->sole()->submitted_at)->toBeNull();

        deleteAs($this->teacher, $activity)
            ->assertRedirect()
            ->assertSessionHasErrors(['activity' => __('activities.errors.has_submissions')]);

        expect(Activity::query()->find($activity->id)?->status)->toBe($activity->status)
            ->and(QuizAttempt::query()->count())->toBe(1)
            ->and($this->deleted)->toBe([])
            ->and(AuditEntry::query()->where('action', 'activity.deleted')->count())->toBe(0);
    });

    it('refuses an activity with one vote, choice, proposal or booking', function (ActivityKind $kind, string $state) {
        $activity = delActivity($kind);
        delSubmit($activity);

        if ($state === 'closed') {
            $this->actingAs($this->teacher)->post(delUrl("activities/{$activity->id}/close"))->assertRedirect();
        }

        deleteAs($this->teacher, $activity)->assertSessionHasErrors('activity');

        expect(Activity::query()->find($activity->id))->not->toBeNull()
            ->and($this->deleted)->toBe([]);
    })->with([
        'poll' => [ActivityKind::Poll],
        'topic selection' => [ActivityKind::TopicSelection],
        'topic proposal' => [ActivityKind::TopicProposal],
        'slot booking' => [ActivityKind::SlotBooking],
    ])->with(['published', 'closed']);

    it('allows the deletion once that booking is cancelled', function () {
        $activity = delActivity(ActivityKind::SlotBooking);
        delSubmit($activity);
        deleteAs($this->teacher, $activity)->assertSessionHasErrors('activity');

        $this->actingAs($this->student)
            ->delete(delUrl("slots/{$activity->id}/booking"))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        deleteAs($this->teacher, $activity)->assertSessionHasNoErrors();

        expect(Activity::query()->find($activity->id))->toBeNull()
            ->and($this->deleted)->toHaveCount(1);
    });

    it('offers the owning teacher deletion only while the activity has no submissions', function () {
        $activity = delActivity(ActivityKind::TopicSelection);
        $can = fn (): bool => (bool) $this->actingAs($this->teacher)
            ->get(delUrl("activities/{$activity->id}"))
            ->viewData('page')['props']['can']['delete'];

        expect($can())->toBeTrue();

        delSubmit($activity);

        expect($can())->toBeFalse();
    });
});

it('UC-040: soft-deletes a published activity without submissions and removes it from every activity list', function (ActivityKind $kind) {
    $activity = delActivity($kind);
    $activity->update(['title' => 'Gone soon']);

    expect(delListed($this->student))->toContain('Gone soon')
        ->and(delListed($this->teacher))->toContain('Gone soon');

    deleteAs($this->teacher, $activity)->assertSessionHasNoErrors();

    expect(Activity::withTrashed()->find($activity->id)?->trashed())->toBeTrue()
        ->and(delListed($this->student))->not->toContain('Gone soon')
        ->and(delListed($this->teacher))->not->toContain('Gone soon');
})->with(fn () => ActivityKind::cases());

it('UC-040: deletes a closed activity without submissions', function () {
    $activity = delActivity(ActivityKind::Poll, 'closed');

    deleteAs($this->teacher, $activity)->assertSessionHasNoErrors();

    expect(Activity::query()->find($activity->id))->toBeNull();
});

describe('UC-040: Given a deleted activity', function () {
    beforeEach(function () {
        NotificationFacade::fake();
        $this->activity = delActivity(ActivityKind::Poll, 'draft');
        $this->actingAs($this->teacher)
            ->post(delUrl("activities/{$this->activity->id}/publish"))
            ->assertSessionHasNoErrors();
        $this->notification = Notification::query()->where('user_id', $this->student->id)->sole();
        expect($this->notification->kind)->toBe(NotificationKind::ActivityPublished);
    });

    it('is 404 for a student who opens it or its activity_published notification', function () {
        deleteAs($this->teacher, $this->activity)->assertSessionHasNoErrors();

        $this->actingAs($this->student)->get(delUrl("activities/{$this->activity->id}"))->assertNotFound();
        $this->actingAs($this->student)->get(delUrl("polls/{$this->activity->id}"))->assertNotFound();
        $this->actingAs($this->student)
            ->post(delUrl("notifications/{$this->notification->id}/open"))
            ->assertNotFound();
        $this->actingAs($this->teacher)->get(delUrl("activities/{$this->activity->id}"))->assertNotFound();
    });

    it('sends no notification and keeps the existing ones', function () {
        Mail::fake();
        $before = Notification::query()->count();
        NotificationFacade::fake();

        deleteAs($this->teacher, $this->activity)->assertSessionHasNoErrors();

        NotificationFacade::assertNothingSent();
        Mail::assertNothingOutgoing();
        expect(Notification::query()->count())->toBe($before)
            ->and(Notification::query()->find($this->notification->id))->not->toBeNull();
    });

    it('offers no way to restore it', function () {
        deleteAs($this->teacher, $this->activity)->assertSessionHasNoErrors();

        $restoreRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => str_contains($route->uri(), 'activities')
                && (str_contains((string) $route->getName(), 'restore') || str_contains($route->uri(), 'restore')));

        expect($restoreRoutes)->toBeEmpty();
        // Neither the activity nor any of its routes find it any more.
        $this->actingAs($this->teacher)->post(delUrl("activities/{$this->activity->id}/publish"))->assertNotFound();
        $this->actingAs($this->teacher)->get(delUrl("activities/{$this->activity->id}/edit"))->assertNotFound();
        deleteAs($this->teacher, $this->activity)->assertNotFound();
        expect(Activity::withTrashed()->find($this->activity->id)?->trashed())->toBeTrue();
    });
});

describe('UC-040: Given teacher A\'s activity', function () {
    it('refuses deletion by teacher B, an org admin or a student and changes nothing', function (string $who, string $state) {
        $activity = delActivity(ActivityKind::Poll, $state);

        $response = deleteAs($this->{$who}, $activity);

        // A draft does not exist for anyone but its owner.
        $state === 'draft' ? $response->assertNotFound() : $response->assertForbidden();
        expect(Activity::query()->find($activity->id))->not->toBeNull()
            ->and($this->deleted)->toBe([]);
    })->with(['otherTeacher', 'admin', 'student'])->with(['draft', 'published']);

    it('does not offer them deletion', function (string $who) {
        $activity = delActivity(ActivityKind::Poll);

        $page = $this->actingAs($this->{$who})->get(delUrl("activities/{$activity->id}"));

        if ($page->status() === 200) {
            expect($page->viewData('page')['props']['can']['delete'])->toBeFalse();
        } else {
            $page->assertStatus(403);
        }
    })->with(['otherTeacher', 'admin', 'student']);
});

it('UC-040: Given an activity of organisation A, a user of organisation B gets not found and nothing changes', function () {
    $activity = delActivity(ActivityKind::Poll);
    $beta = $this->createOrganization(['subdomain' => 'beta']);
    $betaTeacher = $this->asTenant($beta, fn () => User::factory()->teacher()->create());

    $this->actingAs($betaTeacher)
        ->delete($this->tenantUrl($beta, "activities/{$activity->id}"))
        ->assertNotFound();

    $this->actingAsTenant($this->acme);
    expect(Activity::query()->find($activity->id)?->trashed())->toBeFalse()
        ->and($this->deleted)->toBe([]);
});

it('PHASE-4: an activity deleted by its teacher leaves exactly one audit entry activity.deleted with its type', function () {
    $activity = delActivity(ActivityKind::TopicProposal);

    deleteAs($this->teacher, $activity)->assertSessionHasNoErrors();

    $entry = AuditEntry::query()->where('action', 'activity.deleted')->sole();

    expect($entry->actor_type)->toBe(ActorType::User)
        ->and($entry->actor_id)->toBe($this->teacher->id)
        ->and($entry->subject_type)->toBe('activity')
        ->and($entry->subject_id)->toBe($activity->id)
        ->and($entry->organization_id)->toBe($this->acme->id)
        ->and($entry->changes)->toBe(['type' => 'topic_proposal'])
        ->and(AuditEntry::query()->where('subject_id', $activity->id)->count())->toBe(1);
});

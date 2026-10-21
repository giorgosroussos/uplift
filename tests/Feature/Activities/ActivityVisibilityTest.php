<?php

use App\Modules\Activities\Actions\ListActivities;
use App\Modules\Activities\Enums\ActivityStatus;
use App\Modules\Activities\Models\Activity;
use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-022 and OWN-004: who sees an activity. The owning teacher sees all of
| them; enrolled students of an active space see published and closed ones;
| a draft exists for no student; org admins and other teachers see none.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create();
    $this->outsider = User::factory()->student()->create();
    $this->admin = User::factory()->orgAdmin()->create();

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();

    $this->draft = Activity::factory()->for($this->space, 'courseSpace')->create(['title' => 'Draft quiz']);
    $this->published = Activity::factory()->for($this->space, 'courseSpace')->published()->create(['title' => 'Published quiz']);
    $this->closed = Activity::factory()->for($this->space, 'courseSpace')->closed()->create(['title' => 'Closed quiz']);
});

function activityUrl(string $path = ''): string
{
    return test()->tenantUrl(test()->acme, 'activities'.($path === '' ? '' : '/'.$path));
}

/**
 * @return list<string>
 */
function listedTitles(User $user): array
{
    return array_column(app(ListActivities::class)->handle($user), 'title');
}

describe('UC-022: Given a draft activity, Then no student can see it', function () {
    it('is not listed for any student, enrolled or not', function (string $who) {
        $this->actingAs($this->{$who})
            ->get(activityUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Activities/Index')
                ->where('activities', fn ($activities) => ! collect($activities)->contains('id', $this->draft->id)));

        expect(listedTitles($this->{$who}))->not->toContain('Draft quiz');
    })->with(['student', 'outsider']);

    it('does not exist for any student (404)', function (string $who) {
        $this->actingAs($this->{$who})->get(activityUrl($this->draft->id))->assertNotFound();
        $this->actingAs($this->{$who})->get(activityUrl("{$this->draft->id}/edit"))->assertNotFound();
        $this->actingAs($this->{$who})->get(activityUrl("{$this->draft->id}/submissions"))->assertNotFound();

        expect(Gate::forUser($this->{$who})->allows('view', $this->draft))->toBeFalse();
    })->with(['student', 'outsider']);

    it('is hidden from org admins and other teachers too', function (string $who) {
        $this->actingAs($this->{$who})->get(activityUrl($this->draft->id))->assertNotFound();

        expect(listedTitles($this->{$who}))->toBe([]);
    })->with(['admin', 'otherTeacher']);

    it('becomes visible to enrolled students once published', function () {
        $this->draft->transitionTo(ActivityStatus::Published);

        $this->actingAs($this->student)->get(activityUrl($this->draft->id))->assertOk();
    });
});

it('lists every activity of the own course spaces for the teacher, drafts included', function () {
    $other = CourseSpace::factory()->for($this->otherTeacher, 'teacher')->create();
    Activity::factory()->for($other, 'courseSpace')->published()->create(['title' => 'Someone else\'s quiz']);

    $this->actingAs($this->teacher)
        ->get(activityUrl())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Activities/Index')
            ->has('activities', 3)
            ->where('canCreate', true));

    expect(listedTitles($this->teacher))->toEqualCanonicalizing(['Draft quiz', 'Published quiz', 'Closed quiz']);
});

it('shows published and closed activities to enrolled students only', function () {
    expect(listedTitles($this->student))->toEqualCanonicalizing(['Published quiz', 'Closed quiz'])
        ->and(listedTitles($this->outsider))->toBe([]);

    $this->actingAs($this->student)
        ->get(activityUrl($this->published->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Activities/Show')
            ->where('activity.title', 'Published quiz')
            ->where('can.update', false)
            ->where('can.viewSubmissions', false));

    $this->actingAs($this->outsider)->get(activityUrl($this->published->id))->assertForbidden();
    $this->actingAs($this->admin)->get(activityUrl($this->published->id))->assertForbidden();
    $this->actingAs($this->otherTeacher)->get(activityUrl($this->published->id))->assertForbidden();
});

it('hides the activities of an archived course space from students', function () {
    $this->space->transitionTo(CourseSpaceStatus::Archived);

    expect(listedTitles($this->student))->toBe([]);
    $this->actingAs($this->student)->get(activityUrl($this->published->id))->assertNotFound();
});

it('shows the owning teacher the actions of each status', function () {
    $this->actingAs($this->teacher)
        ->get(activityUrl($this->draft->id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activity.status', 'draft')
            ->where('can', ['update' => true, 'publish' => true, 'close' => false, 'viewSubmissions' => true, 'participate' => false]));

    $this->actingAs($this->teacher)
        ->get(activityUrl($this->closed->id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can', ['update' => false, 'publish' => false, 'close' => false, 'viewSubmissions' => true, 'participate' => false]));
});

it('does not find activities of another organisation', function () {
    $beta = $this->createOrganization(['subdomain' => 'beta']);
    $foreign = $this->asTenant($beta, fn () => Activity::factory()->published()->create());

    $this->actingAs($this->teacher)->get(activityUrl($foreign->id))->assertNotFound();
    expect(listedTitles($this->teacher))->not->toContain($foreign->title);
});

it('filters the list by course space and ignores an invalid filter', function () {
    $other = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
    Activity::factory()->for($other, 'courseSpace')->create(['title' => 'Other quiz']);

    $this->actingAs($this->teacher)
        ->get(activityUrl('?course_space='.$other->id))
        ->assertInertia(fn (Assert $page) => $page->has('activities', 1)->where('activities.0.title', 'Other quiz'));

    $this->actingAs($this->teacher)
        ->get(activityUrl('?course_space=not-a-uuid'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('activities', 4));
});

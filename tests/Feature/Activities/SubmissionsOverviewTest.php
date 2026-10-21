<?php

use App\Modules\Activities\Enums\SubmissionStatus;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Activities\Support\Submission;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Activities\FakeQuizType;

/*
| UC-028: the owning teacher lists an activity's submissions, as reported
| by its type, and filters them by status. Nobody else sees them.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);
    FakeQuizType::reset();
    app(ActivityTypes::class)->register(FakeQuizType::class);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create();
    $this->admin = User::factory()->orgAdmin()->create();

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();
    $this->activity = Activity::factory()->for($this->space, 'courseSpace')->published()->create();

    FakeQuizType::$submissions[$this->activity->id] = [
        new Submission('a-1', 'Eleni', now()->subHours(3), SubmissionStatus::Graded, '7.5', '8', 'https://acme.uplift.test/grade/a-1'),
        new Submission('a-2', 'Nikos', now()->subHours(1), SubmissionStatus::NeedsGrading, '4', '8'),
        new Submission('a-3', 'Maria', now()->subHours(2), SubmissionStatus::Submitted),
    ];
});

function submissionsUrl(Activity $activity, string $query = ''): string
{
    return test()->tenantUrl(test()->acme, "activities/{$activity->id}/submissions".($query === '' ? '' : '?'.$query));
}

it('lists every submission newest first with participant, time, status and score', function () {
    $this->actingAs($this->teacher)
        ->get(submissionsUrl($this->activity))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Activities/Submissions')
            ->where('status', null)
            ->where('statuses', ['submitted', 'needs_grading', 'graded'])
            ->has('submissions', 3)
            ->where('submissions.0.id', 'a-2')
            ->where('submissions.1.id', 'a-3')
            ->where('submissions.2', [
                'id' => 'a-1',
                'participant' => 'Eleni',
                'submitted_at' => FakeQuizType::$submissions[$this->activity->id][0]->submittedAt->format(DATE_ATOM),
                'status' => 'graded',
                'earned_points' => '7.5',
                'max_points' => '8',
                'url' => 'https://acme.uplift.test/grade/a-1',
            ]));
});

it('UC-028: Given an activity with 3 submissions of which 1 needs grading, When filtered by needs grading, Then exactly 1 is listed', function () {
    $this->actingAs($this->teacher)
        ->get(submissionsUrl($this->activity, 'status=needs_grading'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', 'needs_grading')
            ->has('submissions', 1)
            ->where('submissions.0.id', 'a-2')
            ->where('submissions.0.status', 'needs_grading'));
});

it('filters by the other statuses and ignores an unknown one', function () {
    $this->actingAs($this->teacher)->get(submissionsUrl($this->activity, 'status=graded'))
        ->assertInertia(fn (Assert $page) => $page->has('submissions', 1)->where('submissions.0.id', 'a-1'));

    $this->actingAs($this->teacher)->get(submissionsUrl($this->activity, 'status=submitted'))
        ->assertInertia(fn (Assert $page) => $page->has('submissions', 1)->where('submissions.0.id', 'a-3'));

    $this->actingAs($this->teacher)->get(submissionsUrl($this->activity, 'status=bogus'))
        ->assertInertia(fn (Assert $page) => $page->where('status', null)->has('submissions', 3));
});

it('UC-028: Given a teacher, When they open submissions of another teacher\'s activity, Then it is refused', function () {
    $this->actingAs($this->otherTeacher)
        ->get(submissionsUrl($this->activity, 'status=needs_grading'))
        ->assertForbidden();
});

it('refuses the submissions overview to students and org admins', function (string $who) {
    $this->actingAs($this->{$who})->get(submissionsUrl($this->activity))->assertForbidden();
})->with(['student', 'admin']);

it('does not find the submissions of another organisation\'s activity', function () {
    $beta = $this->createOrganization(['subdomain' => 'beta']);
    $foreign = $this->asTenant($beta, fn () => Activity::factory()->published()->create());

    $this->actingAs($this->teacher)->get(submissionsUrl($foreign))->assertNotFound();
});

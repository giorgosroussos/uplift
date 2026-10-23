<?php

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\TopicProposalStatus;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\TopicProposal;
use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Browser flow of UC-015 and UC-027: the teacher creates a topic proposal
| activity in the wizard; a student proposes a topic, the teacher rejects
| it with a comment, the student sees the comment and proposes again, and
| the teacher approves the new proposal. In a group activity one member
| proposes for the group and another member sees it.
|
| The browser test server always answers as http://127.0.0.1:<port>, so the
| organisation "127" on the apex domain "0.0.1" stands in for a subdomain.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => '0.0.1', 'app.locale' => 'en']);

    $this->organization = $this->createOrganization(['subdomain' => '127', 'name' => 'Acme School']);
    $this->actingAsTenant($this->organization);
    $this->teacher = User::factory()->teacher()->create(['locale' => 'en']);
    $this->student = User::factory()->student()->create(['locale' => 'en', 'name' => 'Alice Student']);
    $this->classmate = User::factory()->student()->create(['locale' => 'en', 'name' => 'Bob Classmate']);
    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->classmate, 'student')->create();
});

function bpProposalActivity(array $attributes = []): Activity
{
    return Activity::factory()->ofKind(ActivityKind::TopicProposal)->for(test()->space, 'courseSpace')->published()
        ->create(['title' => 'Propose your thesis', 'deadline_at' => now()->addWeek(), ...$attributes]);
}

it('creates a topic proposal activity in the wizard with only its basics', function () {
    $this->actingAs($this->teacher);

    visit('/activities/create')
        ->select('@wizard-course-space', $this->space->id)
        ->select('@wizard-type', 'topic_proposal')
        ->fill('@wizard-title', 'Thesis proposals')
        ->click('@wizard-next')
        ->assertVisible('@wizard-review')
        ->click('@wizard-publish')
        ->assertNoJavaScriptErrors();

    expect(Activity::query()->where('title', 'Thesis proposals')->sole()->isPublished())->toBeTrue();
});

it('proposes, is rejected with a comment, proposes again and is approved', function () {
    $activity = bpProposalActivity();

    $this->actingAs($this->student);
    visit("/activities/{$activity->id}")
        ->click('@activity-open-proposals')
        ->assertSee('Propose your thesis')
        ->assertSeeIn('@proposals-none', 'You have not proposed a topic yet.')
        ->fill('@proposal-title', 'Graph colouring')
        ->fill('@proposal-description', 'Heuristics for sparse graphs')
        ->click('@proposal-submit')
        ->assertSeeIn('@proposal-own-status-0', 'Pending')
        ->assertVisible('@proposals-notice-pending')
        ->assertMissing('@proposals-form')
        ->assertNoJavaScriptErrors();

    $this->actingAs($this->teacher);
    visit("/proposals/{$activity->id}")
        ->assertSeeIn('@proposal-review-0', 'Graph colouring')
        ->assertSeeIn('@proposal-review-0', 'Alice Student')
        ->click('@proposal-reject-0')
        ->assertSee('Add a comment explaining why the proposal is rejected.')
        ->fill('@proposal-comment-0', 'Too broad, narrow it down')
        ->click('@proposal-reject-0')
        ->assertSeeIn('@proposal-review-status-0', 'Rejected')
        ->assertNoJavaScriptErrors();

    $this->actingAs($this->student);
    visit("/proposals/{$activity->id}")
        ->assertSeeIn('@proposal-own-status-0', 'Rejected')
        ->assertSeeIn('@proposal-own-comment-0', 'Too broad, narrow it down')
        ->assertVisible('@proposals-notice-rejected')
        ->fill('@proposal-title', 'Colouring planar graphs')
        ->click('@proposal-submit')
        ->assertSeeIn('@proposal-own-status-0', 'Pending')
        ->assertNoJavaScriptErrors();

    $this->actingAs($this->teacher);
    visit("/proposals/{$activity->id}")
        ->assertSeeIn('@proposal-review-0', 'Colouring planar graphs')
        ->click('@proposal-approve-0')
        ->assertSeeIn('@proposal-review-status-0', 'Approved')
        ->assertNoJavaScriptErrors();

    $this->actingAs($this->student);
    visit("/proposals/{$activity->id}")
        ->assertVisible('@proposals-notice-approved')
        ->assertMissing('@proposals-form')
        ->assertNoJavaScriptErrors();

    expect(TopicProposal::query()->orderBy('created_at')->pluck('status')->all())
        ->toBe([TopicProposalStatus::Rejected, TopicProposalStatus::Approved]);
});

it('lets one member propose for the group and shows it to the other member', function () {
    $activity = bpProposalActivity(['is_group' => true, 'group_size' => 3]);
    $group = Group::factory()->for($this->space, 'courseSpace')->ownedBy($this->student)->create(['name' => 'Team A']);
    GroupMembership::factory()->for($group, 'group')->for($this->student, 'student')->create(['status' => GroupMembershipStatus::Member]);
    GroupMembership::factory()->for($group, 'group')->for($this->classmate, 'student')->create()->transitionTo(GroupMembershipStatus::Member);

    $this->actingAs($this->student);
    visit("/proposals/{$activity->id}")
        ->assertVisible('@proposals-group-activity')
        ->assertSeeIn('@proposals-none', 'Team A has not proposed a topic yet.')
        ->fill('@proposal-title', 'Distributed hash tables')
        ->click('@proposal-submit')
        ->assertSeeIn('@proposal-own-status-0', 'Pending')
        ->assertNoJavaScriptErrors();

    $this->actingAs($this->classmate);
    visit("/proposals/{$activity->id}")
        ->assertSee('Proposals of Team A')
        ->assertSeeIn('@proposal-own-0', 'Distributed hash tables')
        ->assertMissing('@proposals-form')
        ->assertNoJavaScriptErrors();

    expect(TopicProposal::query()->sole()->group_id)->toBe($group->id);
});

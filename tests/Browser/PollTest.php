<?php

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\PollResultsVisibility;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Poll;
use App\Modules\Activities\Models\PollParticipation;
use App\Modules\Activities\Models\PollVote;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Browser flow of UC-013: a student opens a published poll from its
| activity page, chooses up to the allowed number of options, votes once
| and sees the results right away (results after voting); the teacher sees
| the counts without any voter.
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
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);
    Enrolment::factory()->for($space, 'courseSpace')->for($this->student, 'student')->create();

    $this->activity = Activity::factory()->ofKind(ActivityKind::Poll)->for($space, 'courseSpace')->published()
        ->create(['title' => 'Next topic']);
    Poll::factory()->for($this->activity, 'activity')->withOptions(['Graphs', 'Trees', 'Heaps'])
        ->create(['max_choices' => 2, 'results_visibility' => PollResultsVisibility::AfterVote]);
});

it('votes once with up to two options and shows the results', function () {
    $this->actingAs($this->student);

    visit("/activities/{$this->activity->id}")
        ->click('@activity-open-poll')
        ->assertSee('Next topic')
        ->assertSee('Choose up to 2 options.')
        ->assertMissing('@poll-results')
        ->assertVisible('@poll-results-hidden')
        ->click('@poll-choice-1')
        ->click('@poll-choice-3')
        ->assertDisabled('@poll-choice-2')
        ->click('@poll-submit')
        ->assertVisible('@poll-voted')
        ->assertMissing('@poll-vote-form')
        ->assertSeeIn('@poll-participants', '1 participant')
        ->assertSeeIn('@poll-result-1', '1 vote')
        ->assertSeeIn('@poll-result-2', '0 votes')
        ->assertSeeIn('@poll-result-3', '1 vote')
        ->assertNoJavaScriptErrors();

    expect(PollParticipation::query()->count())->toBe(1)
        ->and(PollVote::query()->count())->toBe(2);
});

it('builds a poll in the wizard and publishes it', function () {
    $this->actingAs($this->teacher);

    visit('/activities/create')
        ->select('@wizard-course-space', CourseSpace::query()->sole()->id)
        ->select('@wizard-type', 'poll')
        ->fill('@wizard-title', 'Lunch')
        ->click('@wizard-next')
        ->assertVisible('@poll-settings')
        ->fill('@poll-max-choices', '1')
        ->select('@poll-results-visibility', 'after_close')
        ->click('@wizard-next')
        ->assertVisible('@poll-options')
        ->fill('@poll-option-label-0', 'Pizza')
        ->fill('@poll-option-label-1', 'Salad')
        ->click('@poll-option-add')
        ->fill('@poll-option-label-2', 'Soup')
        ->drag('@poll-option-handle-2', '@poll-option-handle-0')
        ->click('@wizard-next')
        ->assertVisible('@wizard-review')
        ->click('@wizard-publish')
        ->assertNoJavaScriptErrors();

    $activity = Activity::query()->where('title', 'Lunch')->sole();
    $poll = Poll::query()->findOrFail($activity->id);

    expect($activity->isPublished())->toBeTrue()
        ->and($poll->results_visibility)->toBe(PollResultsVisibility::AfterClose)
        ->and($poll->options()->pluck('label')->all())->toBe(['Soup', 'Pizza', 'Salad']);
});

it('shows the teacher the counts and no voter', function () {
    $this->actingAs($this->student);
    visit("/polls/{$this->activity->id}")
        ->click('@poll-choice-2')
        ->click('@poll-submit')
        ->assertVisible('@poll-voted');

    $this->actingAs($this->teacher);

    visit("/activities/{$this->activity->id}")
        ->assertMissing('@activity-submissions')
        ->click('@activity-open-poll')
        ->assertMissing('@poll-vote-form')
        ->assertSeeIn('@poll-participants', '1 participant')
        ->assertSeeIn('@poll-result-2', '1 vote')
        ->assertDontSee('Alice Student')
        ->assertNoJavaScriptErrors();
});

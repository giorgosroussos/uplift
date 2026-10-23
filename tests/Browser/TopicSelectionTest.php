<?php

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Topic;
use App\Modules\Activities\Models\TopicActivity;
use App\Modules\Activities\Models\TopicChoice;
use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Browser flow of UC-014: a student opens a published topic selection from
| its activity page, chooses a topic, switches to another one and cancels;
| in a group activity one member chooses for the group and another member
| sees it; the teacher builds the topics in the wizard and sees who holds
| which topic.
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

function btTopicActivity(array $attributes = []): Activity
{
    $activity = Activity::factory()->ofKind(ActivityKind::TopicSelection)->for(test()->space, 'courseSpace')->published()
        ->create(['title' => 'Thesis topics', 'deadline_at' => now()->addWeek(), ...$attributes]);
    TopicActivity::factory()->for($activity, 'activity')->withTopics(['Graphs' => 1, 'Heaps' => 2])->create();

    return $activity;
}

it('chooses, switches and cancels a topic', function () {
    $activity = btTopicActivity();
    $this->actingAs($this->student);

    visit("/activities/{$activity->id}")
        ->click('@activity-open-topics')
        ->assertSee('Thesis topics')
        ->assertSeeIn('@topics-current', 'You have not chosen a topic yet.')
        ->assertSeeIn('@topic-remaining-0', '1 place left of 1')
        ->click('@topic-choose-0')
        ->assertSeeIn('@topics-current', 'Graphs')
        ->assertVisible('@topic-chosen-0')
        ->assertSeeIn('@topic-remaining-0', 'Full')
        ->click('@topic-choose-1')
        ->assertSeeIn('@topics-current', 'Heaps')
        ->assertSeeIn('@topic-remaining-0', '1 place left of 1')
        ->assertSeeIn('@topic-remaining-1', '1 place left of 2')
        ->click('@topics-cancel')
        ->assertSeeIn('@topics-current', 'You have not chosen a topic yet.')
        ->assertNoJavaScriptErrors();

    expect(TopicChoice::query()->count())->toBe(0);
});

it('lets one member choose for the group and shows it to the other member', function () {
    $activity = btTopicActivity(['is_group' => true, 'group_size' => 3]);
    $group = Group::factory()->for($this->space, 'courseSpace')->ownedBy($this->student)->create(['name' => 'Team A']);
    GroupMembership::factory()->for($group, 'group')->for($this->student, 'student')->create(['status' => GroupMembershipStatus::Member]);
    GroupMembership::factory()->for($group, 'group')->for($this->classmate, 'student')->create()->transitionTo(GroupMembershipStatus::Member);

    $this->actingAs($this->student);
    visit("/topics/{$activity->id}")
        ->assertVisible('@topics-group-activity')
        ->assertSeeIn('@topics-group-choice-0', 'Team A has not chosen a topic yet.')
        ->click('@topic-choose-1')
        ->assertSeeIn('@topics-group-choice-0', 'Heaps')
        ->assertNoJavaScriptErrors();

    $this->actingAs($this->classmate);
    visit("/topics/{$activity->id}")
        ->assertSeeIn('@topics-group-choice-0', 'Topic of Team A')
        ->assertSeeIn('@topics-group-choice-0', 'Heaps')
        ->assertVisible('@topic-chosen-1')
        ->assertNoJavaScriptErrors();

    expect(TopicChoice::query()->sole()->group_id)->toBe($group->id);
});

it('builds the topics in the wizard and shows the teacher who holds them', function () {
    $this->actingAs($this->teacher);

    visit('/activities/create')
        ->select('@wizard-course-space', $this->space->id)
        ->select('@wizard-type', 'topic_selection')
        ->fill('@wizard-title', 'Projects')
        ->click('@wizard-next')
        ->assertVisible('@topic-editor')
        ->fill('@topic-title-0', 'Compilers')
        ->fill('@topic-capacity-0', '2')
        ->click('@topic-add')
        ->fill('@topic-title-1', 'Databases')
        ->click('@wizard-next')
        ->assertVisible('@wizard-review')
        ->click('@wizard-publish')
        ->assertNoJavaScriptErrors();

    $activity = Activity::query()->where('title', 'Projects')->sole();

    expect($activity->isPublished())->toBeTrue()
        ->and(Topic::query()->where('topic_activity_id', $activity->id)->pluck('capacity', 'title')->all())
        ->toBe(['Compilers' => 2, 'Databases' => 1]);

    $choice = new TopicChoice;
    $choice->topic_id = Topic::query()->where('title', 'Compilers')->sole()->id;
    $choice->student_id = $this->student->id;
    $choice->save();

    visit("/activities/{$activity->id}")
        ->click('@activity-open-topics')
        ->assertSeeIn('@topic-holders-0', 'Alice Student')
        ->assertSeeIn('@topic-holders-1', 'Nobody yet')
        ->assertMissing('@topic-choose-0')
        ->assertNoJavaScriptErrors();
});

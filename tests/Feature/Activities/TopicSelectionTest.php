<?php

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Topic;
use App\Modules\Activities\Models\TopicActivity;
use App\Modules\Activities\Models\TopicChoice;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-014 (P4-T3): topic selection. The teacher offers topics with a
| capacity in the activity wizard (SCOPE-011); an enrolled student, or in a
| group activity one member for the group (SCOPE-012, SCOPE-025), selects
| a topic with free places and may switch or cancel until the deadline.
| OWN-008: a student changes only their own or their group's choice; a
| teacher sees choices only of their own activities. The concurrent
| selection criterion is in TopicSelectionConcurrencyTest.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create(['name' => 'Alice Student']);
    $this->classmate = User::factory()->student()->create(['name' => 'Bob Classmate']);
    $this->third = User::factory()->student()->create(['name' => 'Carol Third']);
    $this->outsider = User::factory()->student()->create(['name' => 'Olga Outsider']);
    $this->admin = User::factory()->orgAdmin()->create();

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();

    foreach ([$this->student, $this->classmate, $this->third] as $enrolled) {
        Enrolment::factory()->for($this->space, 'courseSpace')->for($enrolled, 'student')->create();
    }
});

function tsUrl(string $path): string
{
    return test()->tenantUrl(test()->acme, ltrim($path, '/'));
}

/**
 * A topic selection of the course space with the given topics.
 *
 * @param  array<string, int>  $topics  title => capacity
 * @param  array<string, mixed>  $activity  activity attributes
 */
function tsActivity(array $topics = ['Graphs' => 1, 'Trees' => 2], string $state = 'published', array $activity = []): TopicActivity
{
    $factory = Activity::factory()->ofKind(ActivityKind::TopicSelection)->for(test()->space, 'courseSpace');
    $factory = match ($state) {
        'published' => $factory->published(),
        'closed' => $factory->closed(),
        default => $factory,
    };

    return TopicActivity::factory()->for($factory->create($activity), 'activity')->withTopics($topics)->create();
}

function tsGroupActivity(int $groupSize = 3, array $topics = ['Graphs' => 1, 'Trees' => 2]): TopicActivity
{
    return tsActivity($topics, 'published', ['is_group' => true, 'group_size' => $groupSize]);
}

function tsTopic(TopicActivity $topicActivity, string $title): Topic
{
    return Topic::query()->where('topic_activity_id', $topicActivity->activity_id)->where('title', $title)->firstOrFail();
}

/**
 * A group of the course space whose first member is its owner; every
 * member is a current member.
 *
 * @param  list<User>  $members
 */
function tsGroup(string $name, array $members, ?CourseSpace $space = null): Group
{
    $owner = $members[0];
    $group = Group::factory()->for($space ?? test()->space, 'courseSpace')->ownedBy($owner)->create(['name' => $name]);
    GroupMembership::factory()->for($group, 'group')->for($owner, 'student')->create(['status' => GroupMembershipStatus::Member]);

    foreach (array_slice($members, 1) as $member) {
        GroupMembership::factory()->for($group, 'group')->for($member, 'student')->create()
            ->transitionTo(GroupMembershipStatus::Member);
    }

    return $group;
}

/**
 * @param  array<string, mixed>  $extra
 */
function tsChoose(User $user, TopicActivity $topicActivity, Topic|string $topic, array $extra = []): TestResponse
{
    return test()->actingAs($user)->from(tsUrl("topics/{$topicActivity->activity_id}"))
        ->put(tsUrl("topics/{$topicActivity->activity_id}/choice"), [
            'topic_id' => $topic instanceof Topic ? $topic->id : $topic,
            ...$extra,
        ]);
}

/**
 * @param  array<string, mixed>  $extra
 */
function tsCancel(User $user, TopicActivity $topicActivity, array $extra = []): TestResponse
{
    return test()->actingAs($user)->from(tsUrl("topics/{$topicActivity->activity_id}"))
        ->delete(tsUrl("topics/{$topicActivity->activity_id}/choice"), $extra);
}

/**
 * A choice stored directly (as SelectTopic stores it), for setting up.
 */
function tsHold(Topic $topic, ?User $student = null, ?Group $group = null): TopicChoice
{
    $choice = new TopicChoice;
    $choice->topic_id = $topic->id;
    $choice->student_id = $student?->getKey();
    $choice->group_id = $group?->id;
    $choice->save();

    return $choice;
}

/**
 * @return array<string, list<string>> topic title => holder ids (student or group)
 */
function tsHolders(TopicActivity $topicActivity): array
{
    $holders = [];

    foreach ($topicActivity->topics()->get() as $topic) {
        $holders[$topic->title] = $topic->choices()->get()
            ->map(fn (TopicChoice $choice): string => (string) ($choice->student_id ?? $choice->group_id))
            ->sort()->values()->all();
    }

    return $holders;
}

/**
 * @return array<string, int> topic title => remaining places, as the page shows them
 */
function tsRemaining(User $viewer, TopicActivity $topicActivity): array
{
    $props = test()->actingAs($viewer)->get(tsUrl("topics/{$topicActivity->activity_id}"))
        ->assertOk()
        ->viewData('page')['props'];

    return collect($props['topicSelection']['topics'])->mapWithKeys(fn (array $topic): array => [$topic['title'] => $topic['remaining']])->all();
}

describe('building a topic selection in the wizard', function () {
    beforeEach(function () {
        $response = $this->actingAs($this->teacher)->postJson(tsUrl('activities'), [
            'course_space_id' => $this->space->id,
            'type' => 'topic_selection',
            'title' => 'Thesis topics',
            'description' => null,
            'deadline_at' => null,
        ])->assertCreated();

        $this->activity = Activity::query()->findOrFail($response->json('id'));
        $this->ids = [(string) Str::uuid(), (string) Str::uuid()];
    });

    it('offers the topic selection type with a topics step', function () {
        expect(app(ActivityTypes::class)->has(ActivityKind::TopicSelection))->toBeTrue();

        $this->actingAs($this->teacher)->get(tsUrl("activities/{$this->activity->id}/edit"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('types', fn ($types) => collect($types)->contains('topic_selection'))
                ->where('steps.1.key', 'content')
                ->where('steps.1.component', 'Activities/TopicSelection/Topics')
                ->where('steps.1.complete', false)
                ->where('steps.2.key', 'review'));
    });

    it('stores the topics with their places and restores them', function () {
        $this->actingAs($this->teacher)->putJson(tsUrl("activities/{$this->activity->id}/wizard/content"), ['topics' => [
            ['id' => $this->ids[0], 'title' => 'Graphs', 'description' => 'Shortest paths', 'capacity' => 2],
            ['id' => $this->ids[1], 'title' => 'Trees', 'description' => null, 'capacity' => 1],
        ]])->assertOk();

        $topicActivity = TopicActivity::query()->findOrFail($this->activity->id);

        expect($topicActivity->organization_id)->toBe($this->acme->id)
            ->and($topicActivity->topics()->pluck('capacity', 'title')->all())->toBe(['Graphs' => 2, 'Trees' => 1])
            ->and(tsTopic($topicActivity, 'Graphs')->description)->toBe('Shortest paths');

        $this->actingAs($this->teacher)->get(tsUrl("activities/{$this->activity->id}/edit?step=content"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('step', 'content')
                ->where('steps.1.complete', true)
                ->where('data.content.topics.0.id', $this->ids[0])
                ->where('data.content.topics.0.capacity', 2)
                ->where('data.content.topics.1.title', 'Trees'));

        // Leaving a topic out removes it; the same list again changes nothing.
        $this->actingAs($this->teacher)->putJson(tsUrl("activities/{$this->activity->id}/wizard/content"), ['topics' => [
            ['id' => $this->ids[1], 'title' => 'Trees', 'description' => null, 'capacity' => 3],
        ]])->assertOk();

        expect($topicActivity->topics()->pluck('capacity', 'id')->all())->toBe([$this->ids[1] => 3]);
    });

    it('refuses invalid topics with problem details and stores nothing', function (array $topics, string $field) {
        $this->actingAs($this->teacher)->putJson(tsUrl("activities/{$this->activity->id}/wizard/content"), ['topics' => $topics])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonStructure(['errors' => [$field]]);

        expect(Topic::query()->count())->toBe(0);
    })->with([
        'no topics' => [[], 'topics'],
        'empty title' => [[['id' => '0c7f8a49-34a3-4b0b-8a43-6d8c1b1d1c01', 'title' => '', 'capacity' => 1]], 'topics.0.title'],
        'title too long' => [[['id' => '0c7f8a49-34a3-4b0b-8a43-6d8c1b1d1c01', 'title' => str_repeat('x', 256), 'capacity' => 1]], 'topics.0.title'],
        'no places' => [[['id' => '0c7f8a49-34a3-4b0b-8a43-6d8c1b1d1c01', 'title' => 'Graphs', 'capacity' => 0]], 'topics.0.capacity'],
        'too many places' => [[['id' => '0c7f8a49-34a3-4b0b-8a43-6d8c1b1d1c01', 'title' => 'Graphs', 'capacity' => 1001]], 'topics.0.capacity'],
        'no id' => [[['title' => 'Graphs', 'capacity' => 1]], 'topics.0.id'],
    ]);

    it('refuses the id of a topic of another activity', function () {
        $other = tsActivity(['Elsewhere' => 1], 'draft');
        $foreign = tsTopic($other, 'Elsewhere');

        $this->actingAs($this->teacher)->putJson(tsUrl("activities/{$this->activity->id}/wizard/content"), ['topics' => [
            ['id' => $foreign->id, 'title' => 'Hijack', 'capacity' => 5],
        ]])->assertUnprocessable()->assertJsonStructure(['errors' => ['topics.0.id']]);

        expect($foreign->refresh()->title)->toBe('Elsewhere')
            ->and($foreign->capacity)->toBe(1);
    });

    it('publishes only a topic selection with at least one topic', function () {
        $this->actingAs($this->teacher)->post(tsUrl("activities/{$this->activity->id}/publish"))
            ->assertSessionHasErrors('topics');

        expect($this->activity->refresh()->isDraft())->toBeTrue();

        $this->actingAs($this->teacher)->putJson(tsUrl("activities/{$this->activity->id}/wizard/content"), ['topics' => [
            ['id' => $this->ids[0], 'title' => 'Graphs', 'capacity' => 1],
        ]])->assertOk();

        $this->actingAs($this->teacher)->post(tsUrl("activities/{$this->activity->id}/publish"))
            ->assertSessionHasNoErrors();

        expect($this->activity->refresh()->isPublished())->toBeTrue();
    });

    it('lets only the owning teacher edit the topics', function () {
        $this->actingAs($this->otherTeacher)->putJson(tsUrl("activities/{$this->activity->id}/wizard/content"), ['topics' => [
            ['id' => $this->ids[0], 'title' => 'Graphs', 'capacity' => 1],
        ]])->assertNotFound();

        expect(TopicActivity::query()->count())->toBe(0);
    });
});

describe('keeping holders within the places (SCOPE-011)', function () {
    it('refuses removing a chosen topic or giving it fewer places than holders', function () {
        $topicActivity = tsActivity(['Graphs' => 2, 'Trees' => 1]);
        $graphs = tsTopic($topicActivity, 'Graphs');
        $trees = tsTopic($topicActivity, 'Trees');
        tsHold($graphs, $this->student);
        tsHold($graphs, $this->classmate);
        $url = tsUrl("activities/{$topicActivity->activity_id}/wizard/content");

        $this->actingAs($this->teacher)->putJson($url, ['topics' => [
            ['id' => $trees->id, 'title' => 'Trees', 'capacity' => 1],
        ]])->assertUnprocessable()->assertJsonStructure(['errors' => ['topics']]);

        $this->actingAs($this->teacher)->putJson($url, ['topics' => [
            ['id' => $graphs->id, 'title' => 'Graphs', 'capacity' => 1],
            ['id' => $trees->id, 'title' => 'Trees', 'capacity' => 1],
        ]])->assertUnprocessable()->assertJsonStructure(['errors' => ['topics.0.capacity']]);

        expect($topicActivity->topics()->pluck('capacity', 'title')->all())->toBe(['Graphs' => 2, 'Trees' => 1]);

        // More places and an unchosen topic removed are fine.
        $this->actingAs($this->teacher)->putJson($url, ['topics' => [
            ['id' => $graphs->id, 'title' => 'Graphs', 'capacity' => 3],
        ]])->assertOk();

        expect($topicActivity->topics()->pluck('capacity', 'title')->all())->toBe(['Graphs' => 3]);
    });
});

describe('group activities in the wizard basics (UC-022, SCOPE-017)', function () {
    it('creates a group activity with its group size', function () {
        $response = $this->actingAs($this->teacher)->postJson(tsUrl('activities'), [
            'course_space_id' => $this->space->id,
            'type' => 'topic_selection',
            'title' => 'Group topics',
            'is_group' => true,
            'group_size' => 3,
        ])->assertCreated();

        $activity = Activity::query()->findOrFail($response->json('id'));

        expect($activity->is_group)->toBeTrue()
            ->and($activity->group_size)->toBe(3);

        $this->actingAs($this->teacher)->get(tsUrl("activities/{$activity->id}/edit?step=basics"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('basics.is_group', true)
                ->where('basics.group_size', 3));
    });

    it('refuses a group activity without a valid group size', function (mixed $size) {
        $this->actingAs($this->teacher)->postJson(tsUrl('activities'), [
            'course_space_id' => $this->space->id,
            'type' => 'topic_selection',
            'title' => 'Group topics',
            'is_group' => true,
            'group_size' => $size,
        ])->assertUnprocessable()->assertJsonStructure(['errors' => ['group_size']]);

        expect(Activity::query()->count())->toBe(0);
    })->with([null, 1, 101, 'many']);

    it('makes an activity individual again, and keeps the group settings when basics leave them out', function () {
        $topicActivity = tsGroupActivity(4);
        $url = tsUrl("activities/{$topicActivity->activity_id}/wizard/basics");

        $this->actingAs($this->teacher)->putJson($url, ['title' => 'Renamed'])->assertOk();

        expect($topicActivity->activity->refresh()->is_group)->toBeTrue()
            ->and($topicActivity->activity->group_size)->toBe(4);

        $this->actingAs($this->teacher)->putJson($url, ['title' => 'Renamed', 'is_group' => false, 'group_size' => 4])->assertOk();

        expect($topicActivity->activity->refresh()->is_group)->toBeFalse()
            ->and($topicActivity->activity->group_size)->toBeNull();
    });

    it('locks the group settings once anyone has taken part', function () {
        $topicActivity = tsGroupActivity(3);
        tsHold(tsTopic($topicActivity, 'Graphs'), group: tsGroup('G1', [$this->student]));
        $url = tsUrl("activities/{$topicActivity->activity_id}/wizard/basics");

        $this->actingAs($this->teacher)->putJson($url, ['title' => 'Topics', 'is_group' => true, 'group_size' => 2])
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json');

        $this->actingAs($this->teacher)->putJson($url, ['title' => 'Topics', 'is_group' => false])->assertStatus(409);

        expect($topicActivity->activity->refresh()->group_size)->toBe(3)
            ->and($topicActivity->activity->is_group)->toBeTrue();

        // Other basics still change.
        $this->actingAs($this->teacher)->putJson($url, ['title' => 'Renamed', 'is_group' => true, 'group_size' => 3])->assertOk();

        expect($topicActivity->activity->refresh()->title)->toBe('Renamed');
    });
});

describe('selecting a topic (UC-014)', function () {
    it('shows every topic with its remaining places', function () {
        $topicActivity = tsActivity(['Graphs' => 1, 'Trees' => 2]);
        tsHold(tsTopic($topicActivity, 'Trees'), $this->classmate);

        $this->actingAs($this->student)->get(tsUrl("topics/{$topicActivity->activity_id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Activities/TopicSelection/Show')
                ->where('topicSelection.topics.0.title', 'Graphs')
                ->where('topicSelection.topics.0.capacity', 1)
                ->where('topicSelection.topics.0.remaining', 1)
                ->where('topicSelection.topics.1.remaining', 1)
                ->missing('topicSelection.topics.1.holders')
                ->where('choice', null)
                ->where('can.choose', true));
    });

    it('selects a topic with free places', function () {
        $topicActivity = tsActivity();

        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Graphs'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(tsUrl("topics/{$topicActivity->activity_id}"));

        $choice = TopicChoice::query()->sole();

        expect($choice->student_id)->toBe($this->student->id)
            ->and($choice->group_id)->toBeNull()
            ->and($choice->organization_id)->toBe($this->acme->id)
            ->and(tsRemaining($this->student, $topicActivity))->toBe(['Graphs' => 0, 'Trees' => 2]);

        $this->actingAs($this->student)->get(tsUrl("topics/{$topicActivity->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page->where('choice.topic_id', $choice->topic_id));
    });

    it('refuses a full topic and changes nothing', function () {
        $topicActivity = tsActivity(['Graphs' => 1, 'Trees' => 2]);
        $graphs = tsTopic($topicActivity, 'Graphs');
        tsChoose($this->classmate, $topicActivity, $graphs)->assertSessionHasNoErrors();

        tsChoose($this->student, $topicActivity, $graphs)
            ->assertSessionHasErrors(['topic' => __('activities.topics.choose.refused.full')]);

        expect(tsHolders($topicActivity))->toBe(['Graphs' => [$this->classmate->id], 'Trees' => []]);
    });

    it('switches from topic A to topic B, and A regains a place', function () {
        $topicActivity = tsActivity(['Graphs' => 1, 'Trees' => 2]);
        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Graphs'))->assertSessionHasNoErrors();

        expect(tsRemaining($this->student, $topicActivity))->toBe(['Graphs' => 0, 'Trees' => 2]);

        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Trees'))->assertSessionHasNoErrors();

        expect(tsHolders($topicActivity))->toBe(['Graphs' => [], 'Trees' => [$this->student->id]])
            ->and(TopicChoice::query()->count())->toBe(1)
            ->and(tsRemaining($this->student, $topicActivity))->toBe(['Graphs' => 1, 'Trees' => 1]);

        // The freed place can be taken by someone else.
        tsChoose($this->classmate, $topicActivity, tsTopic($topicActivity, 'Graphs'))->assertSessionHasNoErrors();
    });

    it('refuses switching to a full topic and keeps the current one', function () {
        $topicActivity = tsActivity(['Graphs' => 1, 'Trees' => 2]);
        tsHold(tsTopic($topicActivity, 'Graphs'), $this->classmate);
        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Trees'))->assertSessionHasNoErrors();

        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Graphs'))->assertSessionHasErrors('topic');

        expect(tsHolders($topicActivity))->toBe(['Graphs' => [$this->classmate->id], 'Trees' => [$this->student->id]]);
    });

    it('cancels the choice before the deadline and frees the place', function () {
        $topicActivity = tsActivity(['Graphs' => 1], 'published', ['deadline_at' => now()->addDay()]);
        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Graphs'))->assertSessionHasNoErrors();

        tsCancel($this->student, $topicActivity)->assertSessionHasNoErrors();

        expect(TopicChoice::query()->count())->toBe(0)
            ->and(tsRemaining($this->student, $topicActivity))->toBe(['Graphs' => 1]);

        tsCancel($this->student, $topicActivity)->assertSessionHasErrors('topic');
    });

    it('refuses cancelling after the deadline and keeps the topic', function () {
        $topicActivity = tsActivity(['Graphs' => 1, 'Trees' => 1], 'published', ['deadline_at' => now()->addHour()]);
        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Graphs'))->assertSessionHasNoErrors();

        $this->travel(2)->hours();

        tsCancel($this->student, $topicActivity)
            ->assertSessionHasErrors(['topic' => __('activities.topics.choose.refused.deadline_passed')]);
        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Trees'))->assertSessionHasErrors('topic');
        tsChoose($this->classmate, $topicActivity, tsTopic($topicActivity, 'Trees'))->assertSessionHasErrors('topic');

        expect(tsHolders($topicActivity))->toBe(['Graphs' => [$this->student->id], 'Trees' => []]);

        $this->actingAs($this->student)->get(tsUrl("topics/{$topicActivity->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('topicSelection.is_open', false)
                ->where('can.choose', false));
    });

    it('refuses choosing and cancelling once the activity is closed', function () {
        $topicActivity = tsActivity(['Graphs' => 1, 'Trees' => 1]);
        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Graphs'))->assertSessionHasNoErrors();
        $this->actingAs($this->teacher)->post(tsUrl("activities/{$topicActivity->activity_id}/close"));

        tsCancel($this->student, $topicActivity)
            ->assertSessionHasErrors(['topic' => __('activities.topics.choose.refused.closed')]);
        tsChoose($this->classmate, $topicActivity, tsTopic($topicActivity, 'Trees'))->assertSessionHasErrors('topic');

        expect(tsHolders($topicActivity))->toBe(['Graphs' => [$this->student->id], 'Trees' => []]);
    });

    it('refuses a topic of another activity', function () {
        $topicActivity = tsActivity();
        $other = tsActivity(['Elsewhere' => 5]);

        tsChoose($this->student, $topicActivity, tsTopic($other, 'Elsewhere'))->assertSessionHasErrors('topic');
        tsChoose($this->student, $topicActivity, 'not-a-uuid')->assertSessionHasErrors('topic_id');

        expect(TopicChoice::query()->count())->toBe(0);
    });

    it('does not exist as a draft for students', function () {
        $topicActivity = tsActivity(['Graphs' => 1], 'draft');

        $this->actingAs($this->student)->get(tsUrl("topics/{$topicActivity->activity_id}"))->assertNotFound();
        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Graphs'))->assertNotFound();

        expect(TopicChoice::query()->count())->toBe(0);
    });

    it('lets only enrolled students choose', function () {
        $topicActivity = tsActivity();

        tsChoose($this->outsider, $topicActivity, tsTopic($topicActivity, 'Graphs'))->assertForbidden();
        tsChoose($this->teacher, $topicActivity, tsTopic($topicActivity, 'Graphs'))->assertForbidden();

        expect(TopicChoice::query()->count())->toBe(0);
    });

    it('answers 404 for an activity that is no topic selection or of another organisation', function () {
        $poll = Activity::factory()->ofKind(ActivityKind::Poll)->for($this->space, 'courseSpace')->published()->create();
        $this->actingAs($this->student)->get(tsUrl("topics/{$poll->id}"))->assertNotFound();

        $globex = $this->createOrganization(['subdomain' => 'globex']);
        $this->actingAsTenant($globex);
        $foreignTeacher = User::factory()->teacher()->create();
        $foreignSpace = CourseSpace::factory()->for($foreignTeacher, 'teacher')->create();
        $foreign = TopicActivity::factory()->for(
            Activity::factory()->ofKind(ActivityKind::TopicSelection)->for($foreignSpace, 'courseSpace')->published()->create(),
            'activity',
        )->withTopics(['Foreign' => 3])->create();
        $foreignTopic = Topic::query()->where('topic_activity_id', $foreign->activity_id)->sole();
        $this->actingAsTenant($this->acme);

        $this->actingAs($this->student)->get(tsUrl("topics/{$foreign->activity_id}"))->assertNotFound();
        tsChoose($this->student, $foreign, $foreignTopic->id)->assertNotFound();

        expect(TopicChoice::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('lists the choices in the teacher\'s submissions overview', function () {
        $topicActivity = tsActivity();
        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Graphs'))->assertSessionHasNoErrors();

        $this->actingAs($this->teacher)->get(tsUrl("activities/{$topicActivity->activity_id}/submissions"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.participant', 'Alice Student')
                ->where('submissions.0.status', 'submitted')
                ->where('submissions.0.url', tsUrl("topics/{$topicActivity->activity_id}")));
    });

    it('deletes the student\'s choices with their account (SEC-003)', function () {
        $topicActivity = tsActivity();
        tsHold(tsTopic($topicActivity, 'Graphs'), $this->student);

        $this->student->forceDelete();

        expect(TopicChoice::query()->count())->toBe(0);
    });
});

describe('group topic selection (SCOPE-012, SCOPE-025)', function () {
    it('lets one member choose for the group, and every member sees it as the group\'s topic', function () {
        $topicActivity = tsGroupActivity(3);
        $group = tsGroup('Team A', [$this->student, $this->classmate]);

        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Trees'), ['group_id' => $group->id])
            ->assertSessionHasNoErrors();

        $choice = TopicChoice::query()->sole();

        expect($choice->group_id)->toBe($group->id)
            ->and($choice->student_id)->toBeNull();

        $this->actingAs($this->classmate)->get(tsUrl("topics/{$topicActivity->activity_id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('topicSelection.is_group', true)
                ->has('groups', 1)
                ->where('groups.0.id', $group->id)
                ->where('groups.0.name', 'Team A')
                ->where('groups.0.topic_id', tsTopic($topicActivity, 'Trees')->id));

        // Another member may switch or cancel the group's topic.
        tsChoose($this->classmate, $topicActivity, tsTopic($topicActivity, 'Graphs'), ['group_id' => $group->id])
            ->assertSessionHasNoErrors();

        expect(tsHolders($topicActivity))->toBe(['Graphs' => [$group->id], 'Trees' => []]);

        tsCancel($this->student, $topicActivity, ['group_id' => $group->id])->assertSessionHasNoErrors();

        expect(TopicChoice::query()->count())->toBe(0);
    });

    it('lets a student in two groups pick which group acts, and the choice belongs to that group', function () {
        $topicActivity = tsGroupActivity(3);
        $g1 = tsGroup('G1', [$this->student, $this->classmate]);
        $g2 = tsGroup('G2', [$this->third, $this->student]);

        $this->actingAs($this->student)->get(tsUrl("topics/{$topicActivity->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->has('groups', 2)
                ->where('groups.0.id', $g1->id)
                ->where('groups.1.id', $g2->id));

        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Graphs'), ['group_id' => $g2->id])
            ->assertSessionHasNoErrors();

        $choice = TopicChoice::query()->sole();

        expect($choice->group_id)->toBe($g2->id)
            ->and($choice->student_id)->toBeNull();

        // G2's other member sees it as G2's topic; G1's other member sees no topic for G1.
        $this->actingAs($this->third)->get(tsUrl("topics/{$topicActivity->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page->where('groups.0.topic_id', $choice->topic_id));
        $this->actingAs($this->classmate)->get(tsUrl("topics/{$topicActivity->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('groups.0.id', $g1->id)
                ->where('groups.0.topic_id', null)
                ->where('groups.0.in_other_group', true));
    });

    it('refuses a group whose member already takes part through another group', function () {
        $topicActivity = tsGroupActivity(3);
        $g1 = tsGroup('G1', [$this->student, $this->classmate]);
        $g2 = tsGroup('G2', [$this->third, $this->student]);
        tsChoose($this->classmate, $topicActivity, tsTopic($topicActivity, 'Graphs'), ['group_id' => $g1->id])->assertSessionHasNoErrors();

        tsChoose($this->third, $topicActivity, tsTopic($topicActivity, 'Trees'), ['group_id' => $g2->id])
            ->assertSessionHasErrors(['group_id' => __('activities.topics.choose.refused.member_in_other_group')]);
        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Trees'), ['group_id' => $g2->id])
            ->assertSessionHasErrors('group_id');

        expect(tsHolders($topicActivity))->toBe(['Graphs' => [$g1->id], 'Trees' => []]);
    });

    it('lets the other group act once the shared member has left the participating group', function () {
        $topicActivity = tsGroupActivity(3);
        $g1 = tsGroup('G1', [$this->classmate, $this->student]);
        $g2 = tsGroup('G2', [$this->third, $this->student]);
        tsHold(tsTopic($topicActivity, 'Graphs'), group: $g1);

        GroupMembership::query()->where('group_id', $g1->id)->where('student_id', $this->student->id)->sole()
            ->transitionTo(GroupMembershipStatus::Left);

        tsChoose($this->third, $topicActivity, tsTopic($topicActivity, 'Trees'), ['group_id' => $g2->id])
            ->assertSessionHasNoErrors();

        expect(tsHolders($topicActivity))->toBe(['Graphs' => [$g1->id], 'Trees' => [$g2->id]]);
    });

    it('refuses a group with more current members than the group size', function () {
        $topicActivity = tsGroupActivity(2);
        $group = tsGroup('Big', [$this->student, $this->classmate, $this->third]);

        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Trees'), ['group_id' => $group->id])
            ->assertSessionHasErrors(['group_id' => __('activities.topics.choose.refused.group_too_large', ['size' => 2])]);

        expect(TopicChoice::query()->count())->toBe(0);

        // Group size counts current members only: once one leaves, the group may act.
        GroupMembership::query()->where('group_id', $group->id)->where('student_id', $this->third->id)->sole()
            ->transitionTo(GroupMembershipStatus::Left);

        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Trees'), ['group_id' => $group->id])
            ->assertSessionHasNoErrors();
    });

    it('requires one of the student\'s own groups of the space', function () {
        $topicActivity = tsGroupActivity(3);
        $own = tsGroup('Own', [$this->student]);
        $foreign = tsGroup('Others', [$this->classmate, $this->third]);
        $otherSpace = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
        Enrolment::factory()->for($otherSpace, 'courseSpace')->for($this->student, 'student')->create();
        $elsewhere = tsGroup('Elsewhere', [$this->student], $otherSpace);
        $graphs = tsTopic($topicActivity, 'Graphs');

        tsChoose($this->student, $topicActivity, $graphs)
            ->assertSessionHasErrors(['group_id' => __('activities.topics.choose.refused.choose_group')]);
        tsChoose($this->student, $topicActivity, $graphs, ['group_id' => $foreign->id])->assertSessionHasErrors('group_id');
        tsChoose($this->student, $topicActivity, $graphs, ['group_id' => $elsewhere->id])->assertSessionHasErrors('group_id');

        expect(TopicChoice::query()->count())->toBe(0);

        tsChoose($this->student, $topicActivity, $graphs, ['group_id' => $own->id])->assertSessionHasNoErrors();
    });

    it('keeps the choice of a deleted group as history and lets nobody act for it', function () {
        $topicActivity = tsGroupActivity(3);
        $group = tsGroup('Gone', [$this->student]);
        $choice = tsHold(tsTopic($topicActivity, 'Graphs'), group: $group);
        $group->delete();

        tsCancel($this->student, $topicActivity, ['group_id' => $group->id])->assertSessionHasErrors('group_id');
        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Trees'), ['group_id' => $group->id])->assertSessionHasErrors('group_id');

        expect($choice->fresh()?->topic_id)->toBe(tsTopic($topicActivity, 'Graphs')->id);
    });

    it('does not count group size in an individual activity, and ignores a group there', function () {
        $topicActivity = tsActivity();
        $group = tsGroup('G1', [$this->student, $this->classmate]);

        tsChoose($this->student, $topicActivity, tsTopic($topicActivity, 'Graphs'), ['group_id' => $group->id])
            ->assertSessionHasNoErrors();

        expect(TopicChoice::query()->sole()->student_id)->toBe($this->student->id)
            ->and(TopicChoice::query()->sole()->group_id)->toBeNull();
    });
});

describe('OWN-008 on topic choices', function () {
    it('denies a student any change to a choice that is not theirs or their group\'s, and nothing changes', function () {
        $topicActivity = tsGroupActivity(3);
        $theirs = tsGroup('Theirs', [$this->classmate, $this->third]);
        $mine = tsGroup('Mine', [$this->student]);
        $graphs = tsTopic($topicActivity, 'Graphs');
        $trees = tsTopic($topicActivity, 'Trees');
        $choice = tsHold($graphs, group: $theirs);

        // Acting for a group they are not a member of: refused.
        tsCancel($this->student, $topicActivity, ['group_id' => $theirs->id])->assertSessionHasErrors('group_id');
        tsChoose($this->student, $topicActivity, $trees, ['group_id' => $theirs->id])->assertSessionHasErrors('group_id');
        // Acting for their own group never touches the other group's choice.
        tsCancel($this->student, $topicActivity, ['group_id' => $mine->id])->assertSessionHasErrors('topic');

        expect($choice->fresh()?->topic_id)->toBe($graphs->id)
            ->and(TopicChoice::query()->count())->toBe(1);

        $gate = Gate::forUser($this->student);

        expect($gate->allows('view', $choice))->toBeFalse()
            ->and($gate->allows('update', $choice))->toBeFalse()
            ->and($gate->allows('delete', $choice))->toBeFalse()
            ->and($gate->allows('viewAny', TopicChoice::class))->toBeFalse()
            ->and(Gate::forUser($this->classmate)->allows('update', $choice))->toBeTrue();
    });

    it('denies a student another student\'s individual choice and does not show who holds a topic', function () {
        $topicActivity = tsActivity(['Graphs' => 2]);
        $choice = tsHold(tsTopic($topicActivity, 'Graphs'), $this->classmate);

        // Cancelling only ever addresses the student's own choice: they have none.
        tsCancel($this->student, $topicActivity)->assertSessionHasErrors('topic');

        expect($choice->fresh())->not->toBeNull();

        $response = $this->actingAs($this->student)->get(tsUrl("topics/{$topicActivity->activity_id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('choice', null)
                ->missing('topicSelection.topics.0.holders'));

        expect((string) $response->getContent())->not->toContain('Bob Classmate')
            ->and((string) $response->getContent())->not->toContain($choice->id);

        $gate = Gate::forUser($this->student);

        expect($gate->allows('view', $choice))->toBeFalse()
            ->and($gate->allows('update', $choice))->toBeFalse()
            ->and($gate->allows('delete', $choice))->toBeFalse()
            ->and(Gate::forUser($this->classmate)->allows('view', $choice))->toBeTrue();
    });

    it('denies a former member the group\'s choice', function () {
        $topicActivity = tsGroupActivity(3);
        $group = tsGroup('Team', [$this->classmate, $this->student]);
        $choice = tsHold(tsTopic($topicActivity, 'Graphs'), group: $group);
        GroupMembership::query()->where('group_id', $group->id)->where('student_id', $this->student->id)->sole()
            ->transitionTo(GroupMembershipStatus::Left);

        tsCancel($this->student, $topicActivity, ['group_id' => $group->id])->assertSessionHasErrors('group_id');

        expect($choice->fresh())->not->toBeNull()
            ->and(Gate::forUser($this->student)->allows('delete', $choice))->toBeFalse();
    });

    it('shows the owning teacher who holds each topic, and lets them change no choice', function () {
        $topicActivity = tsActivity(['Graphs' => 2, 'Trees' => 1]);
        $choice = tsHold(tsTopic($topicActivity, 'Graphs'), $this->student);

        $this->actingAs($this->teacher)->get(tsUrl("topics/{$topicActivity->activity_id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('topicSelection.topics.0.holders', ['Alice Student'])
                ->where('topicSelection.topics.1.holders', [])
                ->where('can.viewHolders', true)
                ->where('can.choose', false));

        tsCancel($this->teacher, $topicActivity)->assertForbidden();
        tsChoose($this->teacher, $topicActivity, tsTopic($topicActivity, 'Trees'))->assertForbidden();

        $gate = Gate::forUser($this->teacher);

        expect($gate->allows('view', $choice))->toBeTrue()
            ->and($gate->allows('update', $choice))->toBeFalse()
            ->and($gate->allows('delete', $choice))->toBeFalse()
            ->and($choice->fresh()?->topic_id)->toBe(tsTopic($topicActivity, 'Graphs')->id);
    });

    it('denies another teacher and the org admin the choices of an activity they do not own', function (string $who) {
        $viewer = $who === 'teacher' ? $this->otherTeacher : $this->admin;
        $topicActivity = tsActivity(['Graphs' => 2]);
        $choice = tsHold(tsTopic($topicActivity, 'Graphs'), $this->student);

        $this->actingAs($viewer)->get(tsUrl("topics/{$topicActivity->activity_id}"))->assertForbidden();
        $this->actingAs($viewer)->get(tsUrl("activities/{$topicActivity->activity_id}/submissions"))->assertForbidden();
        tsCancel($viewer, $topicActivity)->assertForbidden();

        $gate = Gate::forUser($viewer);

        expect($gate->allows('view', $choice))->toBeFalse()
            ->and($gate->allows('update', $choice))->toBeFalse()
            ->and($gate->allows('delete', $choice))->toBeFalse()
            ->and($gate->allows('viewChoices', $topicActivity))->toBeFalse()
            ->and($choice->fresh())->not->toBeNull();
    })->with(['teacher', 'org admin']);
});

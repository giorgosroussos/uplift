<?php

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\TopicProposalStatus;
use App\Modules\Activities\Events\TopicProposalDecided;
use App\Modules\Activities\Exceptions\InvalidTopicProposalTransition;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\TopicActivity;
use App\Modules\Activities\Models\TopicProposal;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-015 and UC-027 (P4-T4): topic proposals and their approval. An
| enrolled student, or in a group activity one member for the group
| (SCOPE-012, SCOPE-025), proposes a topic before the deadline; it stays
| pending until the owning teacher approves it or rejects it with a
| comment, which notifies the student or group (TopicProposalDecided).
| OWN-009: a student reads only their own or their group's proposals; a
| teacher decides only proposals of their own activities.
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

function tpUrl(string $path): string
{
    return test()->tenantUrl(test()->acme, ltrim($path, '/'));
}

/**
 * A topic proposal activity of the course space.
 *
 * @param  array<string, mixed>  $attributes  activity attributes
 */
function tpActivity(string $state = 'published', array $attributes = [], ?CourseSpace $space = null): Activity
{
    $factory = Activity::factory()->ofKind(ActivityKind::TopicProposal)->for($space ?? test()->space, 'courseSpace');
    $factory = match ($state) {
        'published' => $factory->published(),
        'closed' => $factory->closed(),
        default => $factory,
    };

    return $factory->create($attributes);
}

function tpGroupActivity(int $groupSize = 3): Activity
{
    return tpActivity('published', ['is_group' => true, 'group_size' => $groupSize]);
}

/**
 * @param  list<User>  $members  the first one owns the group
 */
function tpGroup(string $name, array $members): Group
{
    $owner = $members[0];
    $group = Group::factory()->for(test()->space, 'courseSpace')->ownedBy($owner)->create(['name' => $name]);
    GroupMembership::factory()->for($group, 'group')->for($owner, 'student')->create(['status' => GroupMembershipStatus::Member]);

    foreach (array_slice($members, 1) as $member) {
        GroupMembership::factory()->for($group, 'group')->for($member, 'student')->create()
            ->transitionTo(GroupMembershipStatus::Member);
    }

    return $group;
}

/**
 * @param  array<string, mixed>  $input
 */
function tpPropose(User $user, Activity $activity, array $input = []): TestResponse
{
    return test()->actingAs($user)->from(tpUrl("proposals/{$activity->id}"))
        ->post(tpUrl("proposals/{$activity->id}"), [
            'title' => 'Graph colouring',
            'description' => 'Heuristics for large sparse graphs',
            ...$input,
        ]);
}

/**
 * @param  array<string, mixed>  $input
 */
function tpDecide(User $user, TopicProposal $proposal, array $input): TestResponse
{
    $activityId = $proposal->topic_activity_id;

    return test()->actingAs($user)->from(tpUrl("proposals/{$activityId}"))
        ->put(tpUrl("proposals/{$activityId}/{$proposal->id}/decision"), $input);
}

/**
 * A proposal stored directly (as ProposeTopic stores it), then optionally decided.
 */
function tpProposal(Activity $activity, ?User $student = null, ?Group $group = null, TopicProposalStatus $status = TopicProposalStatus::Pending, string $title = 'Stored topic'): TopicProposal
{
    $topicActivity = TopicActivity::forActivity($activity);

    if ($topicActivity === null) {
        $topicActivity = TopicActivity::factory()->for($activity, 'activity')->create();
    }

    $proposal = new TopicProposal(['title' => $title, 'description' => 'Private details']);
    $proposal->topic_activity_id = $topicActivity->activity_id;
    $proposal->student_id = $student?->getKey();
    $proposal->group_id = $group?->id;
    $proposal->save();

    if ($status !== TopicProposalStatus::Pending) {
        $proposal->transitionTo($status, $status === TopicProposalStatus::Rejected ? 'Too broad' : null);
    }

    return $proposal;
}

describe('the topic proposal type in the wizard', function () {
    it('needs only the basics and publishes without content', function () {
        expect(app(ActivityTypes::class)->has(ActivityKind::TopicProposal))->toBeTrue();

        $response = $this->actingAs($this->teacher)->postJson(tpUrl('activities'), [
            'course_space_id' => $this->space->id,
            'type' => 'topic_proposal',
            'title' => 'Propose your thesis',
            'description' => null,
            'deadline_at' => null,
        ])->assertCreated();

        $activity = Activity::query()->findOrFail($response->json('id'));

        $this->actingAs($this->teacher)->get(tpUrl("activities/{$activity->id}/edit"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('types', fn ($types) => collect($types)->contains('topic_proposal'))
                ->has('steps', 2)
                ->where('steps.0.key', 'basics')
                ->where('steps.1.key', 'review'));

        $this->actingAs($this->teacher)->post(tpUrl("activities/{$activity->id}/publish"))->assertSessionHasNoErrors();

        expect($activity->fresh()?->isPublished())->toBeTrue();
    });

    it('counts any proposal as a submission and lists it for the teacher', function () {
        $activity = tpActivity();
        $type = app(ActivityTypes::class)->for($activity);

        expect($type->hasSubmissions($activity))->toBeFalse();

        tpProposal($activity, $this->student, status: TopicProposalStatus::Rejected);

        expect($type->hasSubmissions($activity))->toBeTrue();

        $this->actingAs($this->teacher)->get(tpUrl("activities/{$activity->id}/submissions"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.participant', 'Alice Student')
                ->where('submissions.0.url', route('activities.proposals.show', $activity)));
    });
});

describe('proposing a topic (UC-015)', function () {
    it('creates a pending proposal of the student', function () {
        tpPropose($this->student, $activity = tpActivity())
            ->assertSessionHasNoErrors()
            ->assertRedirect(tpUrl("proposals/{$activity->id}"));

        $proposal = TopicProposal::query()->sole();

        expect($proposal->status)->toBe(TopicProposalStatus::Pending)
            ->and($proposal->student_id)->toBe($this->student->id)
            ->and($proposal->group_id)->toBeNull()
            ->and($proposal->organization_id)->toBe($this->acme->id)
            ->and($proposal->title)->toBe('Graph colouring')
            ->and($proposal->description)->toBe('Heuristics for large sparse graphs')
            ->and($proposal->teacher_comment)->toBeNull();

        $this->actingAs($this->student)->get(tpUrl("proposals/{$activity->id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Activities/TopicProposal/Show')
                ->where('own.proposals.0.status', 'pending')
                ->where('own.proposals.0.title', 'Graph colouring')
                ->where('own.may_propose', false)
                ->where('proposals', [])
                ->where('can.propose', true)
                ->where('can.decide', false));
    });

    it('refuses a second proposal while one is pending', function () {
        $activity = tpActivity();
        tpPropose($this->student, $activity)->assertSessionHasNoErrors();

        tpPropose($this->student, $activity, ['title' => 'Another one'])
            ->assertSessionHasErrors(['proposal' => __('activities.proposals.propose.refused.pending_exists')]);

        expect(TopicProposal::query()->count())->toBe(1)
            ->and(TopicProposal::query()->sole()->title)->toBe('Graph colouring');
    });

    it('keeps a holder to one pending proposal in the database too', function () {
        $activity = tpActivity();
        tpProposal($activity, $this->student);

        expect(fn () => tpProposal($activity, $this->student))->toThrow(UniqueConstraintViolationException::class);
    });

    it('creates a new pending proposal after a rejection, before the deadline', function () {
        $activity = tpActivity(attributes: ['deadline_at' => '2026-11-10 12:00:00']);
        $rejected = tpProposal($activity, $this->student, status: TopicProposalStatus::Rejected);

        tpPropose($this->student, $activity, ['title' => 'Better topic'])->assertSessionHasNoErrors();

        $new = TopicProposal::query()->whereKeyNot($rejected->id)->sole();

        expect($new->status)->toBe(TopicProposalStatus::Pending)
            ->and($new->title)->toBe('Better topic')
            ->and($rejected->fresh()?->status)->toBe(TopicProposalStatus::Rejected)
            ->and($rejected->fresh()?->teacher_comment)->toBe('Too broad');

        $this->actingAs($this->student)->get(tpUrl("proposals/{$activity->id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->has('own.proposals', 2)
                ->where('own.proposals.0.title', 'Better topic')
                ->where('own.proposals.1.status', 'rejected')
                ->where('own.proposals.1.teacher_comment', 'Too broad'));
    });

    it('lets the student propose again after a rejection, shown as possible', function () {
        $activity = tpActivity();
        tpProposal($activity, $this->student, status: TopicProposalStatus::Rejected);

        $this->actingAs($this->student)->get(tpUrl("proposals/{$activity->id}"))
            ->assertInertia(fn (Assert $page) => $page->where('own.may_propose', true));
    });

    it('refuses any further proposal once one is approved', function () {
        $activity = tpActivity();
        tpProposal($activity, $this->student, status: TopicProposalStatus::Approved);

        tpPropose($this->student, $activity)
            ->assertSessionHasErrors(['proposal' => __('activities.proposals.propose.refused.approved_exists')]);

        expect(TopicProposal::query()->count())->toBe(1);

        $this->actingAs($this->student)->get(tpUrl("proposals/{$activity->id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('own.proposals.0.status', 'approved')
                ->where('own.may_propose', false));
    });

    it('refuses proposals after the deadline or once the activity is closed', function (string $state, string $reason) {
        $activity = $state === 'deadline'
            ? tpActivity(attributes: ['deadline_at' => '2026-11-01 12:00:00'])
            : tpActivity('closed');

        tpPropose($this->student, $activity)
            ->assertSessionHasErrors(['proposal' => __("activities.proposals.propose.refused.{$reason}")]);

        expect(TopicProposal::query()->count())->toBe(0);
    })->with([
        'deadline passed' => ['deadline', 'deadline_passed'],
        'closed' => ['closed', 'closed'],
    ]);

    it('validates the title and description', function (array $input, string $field) {
        tpPropose($this->student, tpActivity(), $input)->assertSessionHasErrors($field);

        expect(TopicProposal::query()->count())->toBe(0);
    })->with([
        'no title' => [['title' => ''], 'title'],
        'title too long' => [['title' => str_repeat('x', 256)], 'title'],
        'description too long' => [['description' => str_repeat('x', 5001)], 'description'],
    ]);

    it('lets only enrolled students propose, and keeps drafts hidden', function () {
        tpPropose($this->outsider, tpActivity())->assertForbidden();
        tpPropose($this->teacher, tpActivity())->assertForbidden();
        tpPropose($this->student, tpActivity('draft'))->assertNotFound();

        expect(TopicProposal::query()->count())->toBe(0);
    });

    it('never writes the description to the logs or error messages', function () {
        $activity = tpActivity();
        tpProposal($activity, $this->student);

        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $message) use (&$logged): void {
            $logged[] = $message->message.' '.json_encode($message->context);
        });

        $response = tpPropose($this->student, $activity, ['description' => 'Very personal plan'])
            ->assertSessionHasErrors('proposal');

        expect((string) json_encode(session('errors')))->not->toContain('Very personal plan')
            ->and((string) $response->getContent())->not->toContain('Very personal plan')
            ->and(implode(' ', $logged))->not->toContain('Very personal plan');
    });

    it('deletes the student\'s proposals with their account (SEC-003)', function () {
        $activity = tpActivity();
        tpProposal($activity, $this->student);
        tpProposal($activity, $this->classmate);

        $this->student->forceDelete();

        expect(TopicProposal::query()->pluck('student_id')->all())->toBe([$this->classmate->id]);
    });
});

describe('group proposals (SCOPE-012, SCOPE-025)', function () {
    it('lets a member propose for the group they pick', function () {
        $activity = tpGroupActivity();
        $first = tpGroup('First', [$this->student, $this->classmate]);
        tpGroup('Second', [$this->student]);

        tpPropose($this->student, $activity, ['group_id' => $first->id])->assertSessionHasNoErrors();

        $proposal = TopicProposal::query()->sole();

        expect($proposal->group_id)->toBe($first->id)
            ->and($proposal->student_id)->toBeNull();

        // The other member sees the group's proposal and cannot propose another while it is pending.
        $this->actingAs($this->classmate)->get(tpUrl("proposals/{$activity->id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('groups.0.name', 'First')
                ->where('groups.0.proposals.0.status', 'pending')
                ->where('groups.0.may_propose', false));

        tpPropose($this->classmate, $activity, ['group_id' => $first->id])
            ->assertSessionHasErrors(['proposal' => __('activities.proposals.propose.refused.pending_exists')]);
    });

    it('refuses a proposal for G2 when X takes part through G1', function () {
        $activity = tpGroupActivity();
        $g1 = tpGroup('G1', [$this->student, $this->classmate]);
        $g2 = tpGroup('G2', [$this->student, $this->third]);
        tpPropose($this->student, $activity, ['group_id' => $g1->id])->assertSessionHasNoErrors();

        tpPropose($this->student, $activity, ['group_id' => $g2->id, 'title' => 'Other'])
            ->assertSessionHasErrors(['group_id' => __('activities.proposals.propose.refused.member_in_other_group')]);
        tpPropose($this->third, $activity, ['group_id' => $g2->id, 'title' => 'Other'])
            ->assertSessionHasErrors('group_id');

        expect(TopicProposal::query()->pluck('group_id')->all())->toBe([$g1->id]);
    });

    it('refuses a group without a choice of group, of another student, or larger than the group size', function () {
        $activity = tpGroupActivity(2);
        $large = tpGroup('Large', [$this->student, $this->classmate, $this->third]);
        $theirs = tpGroup('Theirs', [$this->classmate]);

        tpPropose($this->student, $activity)->assertSessionHasErrors(['group_id' => __('activities.proposals.propose.refused.choose_group')]);
        tpPropose($this->student, $activity, ['group_id' => $theirs->id])
            ->assertSessionHasErrors(['group_id' => __('activities.proposals.propose.refused.not_your_group')]);
        tpPropose($this->student, $activity, ['group_id' => $large->id])
            ->assertSessionHasErrors(['group_id' => __('activities.proposals.propose.refused.group_too_large', ['size' => 2])]);

        expect(TopicProposal::query()->count())->toBe(0);
    });
});

describe('deciding proposals (UC-027)', function () {
    it('shows the owning teacher every proposal, pending first', function () {
        $activity = tpActivity();
        tpProposal($activity, $this->classmate, status: TopicProposalStatus::Rejected, title: 'Old');
        tpProposal($activity, $this->student, title: 'Waiting');

        $this->actingAs($this->teacher)->get(tpUrl("proposals/{$activity->id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('proposals', 2)
                ->where('proposals.0.title', 'Waiting')
                ->where('proposals.0.proposer', 'Alice Student')
                ->where('proposals.1.status', 'rejected')
                ->where('can.decide', true)
                ->where('can.propose', false));
    });

    it('approves a pending proposal and notifies the student', function () {
        Event::fake([TopicProposalDecided::class]);
        $proposal = tpProposal(tpActivity(), $this->student);

        tpDecide($this->teacher, $proposal, ['decision' => 'approve'])->assertSessionHasNoErrors();

        expect($proposal->fresh()?->status)->toBe(TopicProposalStatus::Approved)
            ->and($proposal->fresh()?->teacher_comment)->toBeNull();

        Event::assertDispatchedTimes(TopicProposalDecided::class, 1);
        Event::assertDispatched(TopicProposalDecided::class, fn (TopicProposalDecided $event): bool => $event->topicProposalId === $proposal->id
            && $event->organizationId === $this->acme->id
            && $event->status === 'approved');
    });

    it('rejects a pending proposal with a comment and notifies the student', function () {
        Event::fake([TopicProposalDecided::class]);
        $proposal = tpProposal(tpActivity(), $this->student);

        tpDecide($this->teacher, $proposal, ['decision' => 'reject', 'comment' => 'Narrow it down'])->assertSessionHasNoErrors();

        expect($proposal->fresh()?->status)->toBe(TopicProposalStatus::Rejected)
            ->and($proposal->fresh()?->teacher_comment)->toBe('Narrow it down');

        Event::assertDispatched(TopicProposalDecided::class, fn (TopicProposalDecided $event): bool => $event->status === 'rejected');
    });

    it('refuses a rejection without a comment and changes nothing', function (array $input) {
        Event::fake([TopicProposalDecided::class]);
        $proposal = tpProposal(tpActivity(), $this->student);

        tpDecide($this->teacher, $proposal, $input)
            ->assertSessionHasErrors(['comment' => __('activities.proposals.decide.errors.comment_required')]);

        expect($proposal->fresh()?->status)->toBe(TopicProposalStatus::Pending);
        Event::assertNotDispatched(TopicProposalDecided::class);
    })->with([
        'missing' => [['decision' => 'reject']],
        'empty' => [['decision' => 'reject', 'comment' => '']],
        'blank' => [['decision' => 'reject', 'comment' => '   ']],
    ]);

    it('refuses deciding a proposal again and leaves it unchanged', function () {
        Event::fake([TopicProposalDecided::class]);
        $proposal = tpProposal(tpActivity(), $this->student, status: TopicProposalStatus::Approved);

        tpDecide($this->teacher, $proposal, ['decision' => 'reject', 'comment' => 'Changed my mind'])
            ->assertSessionHasErrors(['proposal' => __('activities.proposals.decide.errors.already_decided')]);

        expect($proposal->fresh()?->status)->toBe(TopicProposalStatus::Approved)
            ->and($proposal->fresh()?->teacher_comment)->toBeNull();
        Event::assertNotDispatched(TopicProposalDecided::class);
    });

    it('refuses an unknown decision', function () {
        $proposal = tpProposal(tpActivity(), $this->student);

        tpDecide($this->teacher, $proposal, ['decision' => 'maybe'])->assertSessionHasErrors('decision');

        expect($proposal->fresh()?->status)->toBe(TopicProposalStatus::Pending);
    });

    it('answers 404 for a proposal of another activity', function () {
        $proposal = tpProposal(tpActivity(), $this->student);
        $other = tpActivity();

        $this->actingAs($this->teacher)
            ->put(tpUrl("proposals/{$other->id}/{$proposal->id}/decision"), ['decision' => 'approve'])
            ->assertNotFound();

        expect($proposal->fresh()?->status)->toBe(TopicProposalStatus::Pending);
    });
});

describe('the ENT-TopicProposal state model', function () {
    it('rejects every transition not in the state model and keeps the state', function (TopicProposalStatus $from, TopicProposalStatus $to) {
        $proposal = tpProposal(tpActivity(), $this->student, status: $from);

        expect(fn () => $proposal->transitionTo($to, 'x'))->toThrow(InvalidTopicProposalTransition::class);
        expect($proposal->fresh()?->status)->toBe($from);

        if ($from === $to) {
            return;
        }

        // Also when the status is set directly.
        $proposal = $proposal->fresh();
        $proposal->status = $to;

        expect(fn () => $proposal->save())->toThrow(InvalidTopicProposalTransition::class);
        expect($proposal->fresh()?->status)->toBe($from);
    })->with([
        'approved -> rejected' => [TopicProposalStatus::Approved, TopicProposalStatus::Rejected],
        'approved -> pending' => [TopicProposalStatus::Approved, TopicProposalStatus::Pending],
        'rejected -> approved' => [TopicProposalStatus::Rejected, TopicProposalStatus::Approved],
        'rejected -> pending' => [TopicProposalStatus::Rejected, TopicProposalStatus::Pending],
        'pending -> pending' => [TopicProposalStatus::Pending, TopicProposalStatus::Pending],
    ]);

    it('allows pending -> approved and pending -> rejected', function (TopicProposalStatus $to) {
        $proposal = tpProposal(tpActivity(), $this->student);
        $proposal->transitionTo($to, 'ok');

        expect($proposal->fresh()?->status)->toBe($to);
    })->with([TopicProposalStatus::Approved, TopicProposalStatus::Rejected]);

    it('creates proposals only as pending', function () {
        $activity = tpActivity();
        TopicActivity::factory()->for($activity, 'activity')->create();
        $proposal = new TopicProposal(['title' => 'Sneaky']);
        $proposal->topic_activity_id = $activity->id;
        $proposal->student_id = $this->student->id;
        $proposal->status = TopicProposalStatus::Approved;

        expect(fn () => $proposal->save())->toThrow(InvalidTopicProposalTransition::class)
            ->and(TopicProposal::query()->count())->toBe(0);
    });

    it('keeps the proposal content out of the transition error', function () {
        $proposal = tpProposal(tpActivity(), $this->student, status: TopicProposalStatus::Approved);

        try {
            $proposal->transitionTo(TopicProposalStatus::Rejected);
        } catch (InvalidTopicProposalTransition $e) {
            expect($e->getMessage())->not->toContain('Private details')
                ->and($e->getMessage())->not->toContain('Stored topic');
        }
    });
});

describe('OWN-009 on topic proposals', function () {
    it('denies a student the proposals of another student, and nothing changes', function () {
        $activity = tpActivity();
        $theirs = tpProposal($activity, $this->classmate, title: 'Bob\'s secret topic');

        $response = $this->actingAs($this->student)->get(tpUrl("proposals/{$activity->id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('own.proposals', [])
                ->where('proposals', []));

        expect((string) $response->getContent())->not->toContain('Bob\'s secret topic')
            ->and((string) $response->getContent())->not->toContain($theirs->id);

        tpDecide($this->student, $theirs, ['decision' => 'approve'])->assertForbidden();

        $gate = Gate::forUser($this->student);

        expect($gate->allows('view', $theirs))->toBeFalse()
            ->and($gate->allows('decide', $theirs))->toBeFalse()
            ->and($gate->allows('update', $theirs))->toBeFalse()
            ->and($gate->allows('delete', $theirs))->toBeFalse()
            ->and($gate->allows('viewAny', TopicProposal::class))->toBeFalse()
            ->and($gate->allows('viewProposals', TopicActivity::forActivityOrNew($activity)))->toBeFalse()
            ->and(Gate::forUser($this->classmate)->allows('view', $theirs))->toBeTrue()
            ->and($theirs->fresh()?->status)->toBe(TopicProposalStatus::Pending);
    });

    it('denies a student the proposals of a group they are not (or no longer) a member of', function () {
        $activity = tpGroupActivity();
        $group = tpGroup('Team', [$this->classmate, $this->student]);
        $proposal = tpProposal($activity, group: $group);

        expect(Gate::forUser($this->student)->allows('view', $proposal))->toBeTrue()
            ->and(Gate::forUser($this->third)->allows('view', $proposal))->toBeFalse();

        GroupMembership::query()->where('group_id', $group->id)->where('student_id', $this->student->id)->sole()
            ->transitionTo(GroupMembershipStatus::Left);

        expect(Gate::forUser($this->student)->allows('view', $proposal))->toBeFalse();

        $this->actingAs($this->student)->get(tpUrl("proposals/{$activity->id}"))
            ->assertInertia(fn (Assert $page) => $page->where('groups', []));
    });

    it('lets the owning teacher decide but neither edit nor delete', function () {
        $proposal = tpProposal(tpActivity(), $this->student);
        $gate = Gate::forUser($this->teacher);

        expect($gate->allows('view', $proposal))->toBeTrue()
            ->and($gate->allows('decide', $proposal))->toBeTrue()
            ->and($gate->allows('update', $proposal))->toBeFalse()
            ->and($gate->allows('delete', $proposal))->toBeFalse();
    });

    it('denies another teacher and the org admin the proposals of an activity they do not own, and nothing changes', function (string $who) {
        Event::fake([TopicProposalDecided::class]);
        $viewer = $who === 'teacher' ? $this->otherTeacher : $this->admin;
        $activity = tpActivity();
        $proposal = tpProposal($activity, $this->student);

        $this->actingAs($viewer)->get(tpUrl("proposals/{$activity->id}"))->assertForbidden();
        $this->actingAs($viewer)->get(tpUrl("activities/{$activity->id}/submissions"))->assertForbidden();
        tpDecide($viewer, $proposal, ['decision' => 'approve'])->assertForbidden();
        tpDecide($viewer, $proposal, ['decision' => 'reject', 'comment' => 'No'])->assertForbidden();

        $gate = Gate::forUser($viewer);

        expect($gate->allows('view', $proposal))->toBeFalse()
            ->and($gate->allows('decide', $proposal))->toBeFalse()
            ->and($proposal->fresh()?->status)->toBe(TopicProposalStatus::Pending)
            ->and($proposal->fresh()?->teacher_comment)->toBeNull();
        Event::assertNotDispatched(TopicProposalDecided::class);
    })->with(['teacher', 'org admin']);

    it('denies a teacher the proposals of another teacher\'s course space', function () {
        $foreignSpace = CourseSpace::factory()->for($this->otherTeacher, 'teacher')->create();
        $proposal = tpProposal(tpActivity(space: $foreignSpace), $this->student);

        tpDecide($this->teacher, $proposal, ['decision' => 'approve'])->assertForbidden();

        expect(Gate::forUser($this->teacher)->allows('decide', $proposal))->toBeFalse()
            ->and(Gate::forUser($this->otherTeacher)->allows('decide', $proposal))->toBeTrue()
            ->and($proposal->fresh()?->status)->toBe(TopicProposalStatus::Pending);
    });

    it('does not find proposals of another organisation', function () {
        $proposal = tpProposal(tpActivity(), $this->student);
        $other = $this->createOrganization(['subdomain' => 'other']);
        $this->actingAsTenant($other);
        $foreignTeacher = User::factory()->teacher()->create();

        $this->actingAs($foreignTeacher)
            ->put($this->tenantUrl($other, "proposals/{$proposal->topic_activity_id}/{$proposal->id}/decision"), ['decision' => 'approve'])
            ->assertNotFound();

        $this->actingAsTenant($this->acme);

        expect($proposal->fresh()?->status)->toBe(TopicProposalStatus::Pending);
    });
});

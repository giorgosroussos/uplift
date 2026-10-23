<?php

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\ActivityStatus;
use App\Modules\Activities\Enums\PollResultsVisibility;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Poll;
use App\Modules\Activities\Models\PollOption;
use App\Modules\Activities\Models\PollParticipation;
use App\Modules\Activities\Models\PollVote;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-013 (P4-T2): polls. The teacher builds a poll in the activity wizard
| (settings: number of choices and results visibility; content: ordered
| options); an enrolled student votes once in an open poll and sees the
| results as the teacher set them (SCOPE-013). Polls are anonymous: who
| voted (ENT-PollParticipation) and what was voted (ENT-PollVote) are never
| linked (DATA-006), and nobody sees who voted or what anyone voted
| (SEC-009, OWN-011).
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
    $this->outsider = User::factory()->student()->create();
    $this->admin = User::factory()->orgAdmin()->create();

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->student, 'student')->create();
    Enrolment::factory()->for($this->space, 'courseSpace')->for($this->classmate, 'student')->create();
});

function plUrl(string $path): string
{
    return test()->tenantUrl(test()->acme, ltrim($path, '/'));
}

/**
 * A poll of the course space with the given options.
 *
 * @param  list<string>  $labels
 * @param  array<string, mixed>  $attributes  poll attributes
 * @param  array<string, mixed>  $activity  activity attributes
 */
function plPoll(array $labels = ['A', 'B', 'C'], array $attributes = [], string $state = 'published', array $activity = []): Poll
{
    $factory = Activity::factory()->ofKind(ActivityKind::Poll)->for(test()->space, 'courseSpace');
    $factory = match ($state) {
        'published' => $factory->published(),
        'closed' => $factory->closed(),
        default => $factory,
    };

    return Poll::factory()->for($factory->create($activity), 'activity')->withOptions($labels)->create($attributes);
}

function plOption(Poll $poll, string $label): PollOption
{
    return $poll->options()->where('label', $label)->firstOrFail();
}

/**
 * @param  list<string>  $optionIds
 */
function plVote(User $user, Poll $poll, array $optionIds): TestResponse
{
    return test()->actingAs($user)->post(plUrl("polls/{$poll->activity_id}/vote"), ['options' => $optionIds]);
}

/**
 * @param  array<string, mixed>  $data
 */
function plAutosave(User $user, Activity $activity, string $step, array $data): TestResponse
{
    return test()->actingAs($user)->putJson(plUrl("activities/{$activity->id}/wizard/{$step}"), $data);
}

/**
 * @return array<string, int>
 */
function plTally(Poll $poll): array
{
    return $poll->options()->withCount('votes')->get()
        ->mapWithKeys(fn (PollOption $option): array => [$option->label => (int) $option->getAttribute('votes_count')])
        ->all();
}

/**
 * Casts a vote directly (as CastPollVote stores it), for setting up tallies.
 *
 * @param  list<string>  $labels
 */
function plCast(User $student, Poll $poll, array $labels): void
{
    $participation = new PollParticipation;
    $participation->poll_id = $poll->activity_id;
    $participation->student_id = (string) $student->getKey();
    $participation->save();

    foreach ($labels as $label) {
        $vote = new PollVote;
        $vote->poll_option_id = plOption($poll, $label)->id;
        $vote->save();
    }
}

/**
 * Asserts that a response says nothing about who voted: no id, name or
 * email of any student (other than the viewer, whose own account is on
 * every page) and no participation or vote record.
 */
function plAssertNoVoterIdentity(TestResponse $response, ?User $viewer = null): void
{
    $body = (string) $response->getContent();

    foreach (User::query()->where('role', 'student')->get() as $student) {
        if ($viewer !== null && $student->is($viewer)) {
            continue;
        }

        expect($body)->not->toContain((string) $student->getKey())
            ->and($body)->not->toContain($student->email)
            ->and($body)->not->toContain($student->name);
    }

    foreach (PollParticipation::query()->pluck('id') as $id) {
        expect($body)->not->toContain((string) $id);
    }

    foreach (PollVote::query()->pluck('id') as $id) {
        expect($body)->not->toContain((string) $id);
    }
}

describe('building a poll in the wizard', function () {
    beforeEach(function () {
        $response = $this->actingAs($this->teacher)->postJson(plUrl('activities'), [
            'course_space_id' => $this->space->id,
            'type' => 'poll',
            'title' => 'Next topic',
            'description' => null,
            'deadline_at' => null,
        ])->assertCreated();

        $this->activity = Activity::query()->findOrFail($response->json('id'));
        $this->ids = [(string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid()];
    });

    it('offers the poll type with a settings and a content step', function () {
        $this->actingAs($this->teacher)->get(plUrl("activities/{$this->activity->id}/edit"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('types', fn ($types) => collect($types)->contains('poll'))
                ->where('steps.1.component', 'Activities/Poll/Settings')
                ->where('steps.2.component', 'Activities/Poll/Options'));
    });

    it('stores the settings and the options in order and restores them', function () {
        plAutosave($this->teacher, $this->activity, 'settings', ['max_choices' => 2, 'results_visibility' => 'after_close'])->assertOk();
        plAutosave($this->teacher, $this->activity, 'content', ['options' => [
            ['id' => $this->ids[0], 'label' => 'Graphs'],
            ['id' => $this->ids[1], 'label' => 'Trees'],
            ['id' => $this->ids[2], 'label' => 'Heaps'],
        ]])->assertOk();

        $poll = Poll::query()->findOrFail($this->activity->id);

        expect($poll->max_choices)->toBe(2)
            ->and($poll->results_visibility)->toBe(PollResultsVisibility::AfterClose)
            ->and($poll->options()->pluck('label')->all())->toBe(['Graphs', 'Trees', 'Heaps'])
            ->and($poll->options()->pluck('id')->all())->toBe($this->ids);

        // Reordering and removing keep the ids of the remaining options.
        plAutosave($this->teacher, $this->activity, 'content', ['options' => [
            ['id' => $this->ids[2], 'label' => 'Heaps'],
            ['id' => $this->ids[0], 'label' => 'Graphs'],
        ]])->assertOk();

        expect($poll->options()->pluck('id')->all())->toBe([$this->ids[2], $this->ids[0]]);

        $this->actingAs($this->teacher)->get(plUrl("activities/{$this->activity->id}/edit"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('data.settings', ['max_choices' => 2, 'results_visibility' => 'after_close'])
                ->where('data.content.options', [
                    ['id' => $this->ids[2], 'label' => 'Heaps'],
                    ['id' => $this->ids[0], 'label' => 'Graphs'],
                ]));
    });

    it('refuses invalid settings', function (array $settings, string $field) {
        plAutosave($this->teacher, $this->activity, 'settings', $settings)
            ->assertStatus(422)
            ->assertJsonPath('errors', fn (array $errors) => is_string($errors[$field][0] ?? null));

        expect(Poll::query()->find($this->activity->id))->toBeNull();
    })->with([
        'no choice' => [['max_choices' => 0, 'results_visibility' => 'after_vote'], 'max_choices'],
        'too many choices' => [['max_choices' => 21, 'results_visibility' => 'after_vote'], 'max_choices'],
        'unknown visibility' => [['max_choices' => 1, 'results_visibility' => 'never'], 'results_visibility'],
    ]);

    it('refuses options before the settings', function () {
        plAutosave($this->teacher, $this->activity, 'content', ['options' => [
            ['id' => $this->ids[0], 'label' => 'Yes'],
            ['id' => $this->ids[1], 'label' => 'No'],
        ]])->assertStatus(422)->assertJsonPath('errors.options.0', __('activities.poll.errors.settings_first'));
    });

    it('refuses invalid options and stores nothing', function (array $options, string $field) {
        plAutosave($this->teacher, $this->activity, 'settings', ['max_choices' => 1, 'results_visibility' => 'after_vote'])->assertOk();

        $resolved = array_map(fn (array $option): array => [
            'id' => is_int($option['id']) ? $this->ids[$option['id']] : $option['id'],
            'label' => $option['label'],
        ], $options);

        plAutosave($this->teacher, $this->activity, 'content', ['options' => $resolved])
            ->assertStatus(422)
            ->assertJsonPath('errors', fn (array $errors) => is_string($errors[$field][0] ?? null));

        expect(PollOption::query()->count())->toBe(0);
    })->with([
        'one option' => [[['id' => 0, 'label' => 'Only']], 'options'],
        'empty label' => [[['id' => 0, 'label' => 'Yes'], ['id' => 1, 'label' => '']], 'options.1.label'],
        'duplicate label' => [[['id' => 0, 'label' => 'Yes'], ['id' => 1, 'label' => 'yes']], 'options.1.label'],
        'duplicate id' => [[['id' => 0, 'label' => 'Yes'], ['id' => 0, 'label' => 'No']], 'options.1.id'],
        'no uuid' => [[['id' => 'x', 'label' => 'Yes'], ['id' => 1, 'label' => 'No']], 'options.0.id'],
        'too long' => [[['id' => 0, 'label' => str_repeat('a', 256)], ['id' => 1, 'label' => 'No']], 'options.0.label'],
    ]);

    it('refuses more than 20 options', function () {
        plAutosave($this->teacher, $this->activity, 'settings', ['max_choices' => 1, 'results_visibility' => 'after_vote'])->assertOk();

        $options = array_map(fn (int $i): array => ['id' => (string) Str::uuid(), 'label' => "Option {$i}"], range(1, 21));

        plAutosave($this->teacher, $this->activity, 'content', ['options' => $options])
            ->assertStatus(422)
            ->assertJsonPath('errors.options.0', __('activities.poll.errors.max_options', ['max' => 20]));
    });

    it('refuses the id of an option of another poll', function () {
        $other = plPoll(['X', 'Y']);
        plAutosave($this->teacher, $this->activity, 'settings', ['max_choices' => 1, 'results_visibility' => 'after_vote'])->assertOk();

        plAutosave($this->teacher, $this->activity, 'content', ['options' => [
            ['id' => plOption($other, 'X')->id, 'label' => 'Hijacked'],
            ['id' => $this->ids[1], 'label' => 'No'],
        ]])->assertStatus(422)->assertJsonPath('errors', fn (array $errors) => ($errors['options.0.id'][0] ?? null) === __('activities.poll.errors.foreign_id'));

        expect(plOption($other, 'X')->poll_id)->toBe($other->activity_id)
            ->and(PollOption::query()->where('poll_id', $this->activity->id)->count())->toBe(0);
    });

    it('publishes only a poll with settings, at least two options and no more choices than options', function () {
        $this->actingAs($this->teacher)->post(plUrl("activities/{$this->activity->id}/publish"))
            ->assertSessionHasErrors('settings');

        plAutosave($this->teacher, $this->activity, 'settings', ['max_choices' => 3, 'results_visibility' => 'after_vote'])->assertOk();
        $this->actingAs($this->teacher)->post(plUrl("activities/{$this->activity->id}/publish"))
            ->assertSessionHasErrors('options');

        plAutosave($this->teacher, $this->activity, 'content', ['options' => [
            ['id' => $this->ids[0], 'label' => 'Yes'],
            ['id' => $this->ids[1], 'label' => 'No'],
        ]])->assertOk();
        $this->actingAs($this->teacher)->post(plUrl("activities/{$this->activity->id}/publish"))
            ->assertSessionHasErrors('max_choices');

        expect($this->activity->fresh()->status)->toBe(ActivityStatus::Draft);

        plAutosave($this->teacher, $this->activity, 'settings', ['max_choices' => 2, 'results_visibility' => 'after_vote'])->assertOk();
        $this->actingAs($this->teacher)->post(plUrl("activities/{$this->activity->id}/publish"))
            ->assertSessionHasNoErrors();

        expect($this->activity->fresh()->status)->toBe(ActivityStatus::Published);
    });

    it('locks the options and the number of choices once students have voted', function () {
        plAutosave($this->teacher, $this->activity, 'settings', ['max_choices' => 1, 'results_visibility' => 'after_vote'])->assertOk();
        plAutosave($this->teacher, $this->activity, 'content', ['options' => [
            ['id' => $this->ids[0], 'label' => 'Yes'],
            ['id' => $this->ids[1], 'label' => 'No'],
        ]])->assertOk();
        $this->actingAs($this->teacher)->post(plUrl("activities/{$this->activity->id}/publish"));
        $poll = Poll::query()->findOrFail($this->activity->id);
        plCast($this->student, $poll, ['Yes']);

        plAutosave($this->teacher, $this->activity, 'content', ['options' => [
            ['id' => $this->ids[0], 'label' => 'Yes, sure'],
            ['id' => $this->ids[1], 'label' => 'No'],
        ]])->assertStatus(409);
        plAutosave($this->teacher, $this->activity, 'content', ['options' => [
            ['id' => $this->ids[0], 'label' => 'Yes'],
        ]])->assertStatus(409);
        plAutosave($this->teacher, $this->activity, 'settings', ['max_choices' => 2, 'results_visibility' => 'after_vote'])->assertStatus(409);

        expect($poll->options()->pluck('label')->all())->toBe(['Yes', 'No'])
            ->and($poll->fresh()->max_choices)->toBe(1);

        // Order and results visibility do not change what the votes mean.
        plAutosave($this->teacher, $this->activity, 'content', ['options' => [
            ['id' => $this->ids[1], 'label' => 'No'],
            ['id' => $this->ids[0], 'label' => 'Yes'],
        ]])->assertOk();
        plAutosave($this->teacher, $this->activity, 'settings', ['max_choices' => 1, 'results_visibility' => 'after_close'])->assertOk();

        expect($poll->options()->pluck('label')->all())->toBe(['No', 'Yes'])
            ->and(plTally($poll))->toBe(['No' => 0, 'Yes' => 1]);
    });

    it('lets only the owning teacher edit the poll', function () {
        plAutosave($this->otherTeacher, $this->activity, 'settings', ['max_choices' => 1, 'results_visibility' => 'after_vote'])->assertNotFound();
        plAutosave($this->student, $this->activity, 'settings', ['max_choices' => 1, 'results_visibility' => 'after_vote'])->assertNotFound();

        expect(Poll::query()->find($this->activity->id))->toBeNull();
    });
});

describe('voting (UC-013)', function () {
    it('records a vote as a participation and anonymous votes', function () {
        $poll = plPoll(['A', 'B', 'C'], ['max_choices' => 2]);

        plVote($this->student, $poll, [plOption($poll, 'A')->id, plOption($poll, 'C')->id])
            ->assertRedirect(plUrl("polls/{$poll->activity_id}"))
            ->assertSessionHasNoErrors();

        expect(PollParticipation::query()->where('poll_id', $poll->activity_id)->pluck('student_id')->all())->toBe([$this->student->id])
            ->and(plTally($poll))->toBe(['A' => 1, 'B' => 0, 'C' => 1]);
    });

    // UC-013: Given a student who has voted, When they vote again, Then it
    // is refused and the tally is unchanged.
    it('refuses a second vote and leaves the tally unchanged', function () {
        $poll = plPoll(['A', 'B']);
        plVote($this->student, $poll, [plOption($poll, 'A')->id])->assertSessionHasNoErrors();

        plVote($this->student, $poll, [plOption($poll, 'B')->id])
            ->assertSessionHasErrors(['options' => __('activities.poll.vote.refused.already_voted')]);
        plVote($this->student, $poll, [plOption($poll, 'A')->id])
            ->assertSessionHasErrors(['options' => __('activities.poll.vote.refused.already_voted')]);

        expect(plTally($poll))->toBe(['A' => 1, 'B' => 0])
            ->and(PollParticipation::query()->count())->toBe(1);
    });

    it('keeps one participation per student and poll in the database', function () {
        $poll = plPoll(['A', 'B']);
        plCast($this->student, $poll, ['A']);

        expect(fn () => plCast($this->student, $poll, ['B']))->toThrow(UniqueConstraintViolationException::class);
    });

    // UC-013: Given a poll allowing at most 2 options, When a student
    // selects 3, Then the vote is rejected.
    it('rejects more options than allowed and stores nothing', function () {
        $poll = plPoll(['A', 'B', 'C'], ['max_choices' => 2]);

        plVote($this->student, $poll, $poll->options()->pluck('id')->all())
            ->assertSessionHasErrors(['options' => __('activities.poll.vote.refused.too_many', ['max' => 2])]);

        expect(plTally($poll))->toBe(['A' => 0, 'B' => 0, 'C' => 0])
            ->and(PollParticipation::query()->count())->toBe(0);

        // The student can still vote within the limit.
        plVote($this->student, $poll, [plOption($poll, 'A')->id, plOption($poll, 'B')->id])->assertSessionHasNoErrors();
        expect(plTally($poll))->toBe(['A' => 1, 'B' => 1, 'C' => 0]);
    });

    it('refuses an empty vote, a repeated option and options of another poll', function (string $case) {
        $poll = plPoll(['A', 'B'], ['max_choices' => 2]);
        $other = plPoll(['X', 'Y']);

        $options = match ($case) {
            'empty' => [],
            'repeated' => [plOption($poll, 'A')->id, plOption($poll, 'A')->id],
            'foreign' => [plOption($other, 'X')->id],
            'no uuid' => ['not-an-id'],
        };

        plVote($this->student, $poll, $options)->assertSessionHasErrors('options*');

        expect(PollParticipation::query()->count())->toBe(0)
            ->and(PollVote::query()->count())->toBe(0);
    })->with(['empty', 'repeated', 'foreign', 'no uuid']);

    it('refuses a vote in a closed poll', function () {
        $poll = plPoll(['A', 'B'], [], 'closed');

        plVote($this->student, $poll, [plOption($poll, 'A')->id])
            ->assertSessionHasErrors(['options' => __('activities.poll.vote.refused.closed')]);

        expect(PollParticipation::query()->count())->toBe(0)
            ->and(PollVote::query()->count())->toBe(0);
    });

    it('refuses a vote after the deadline (server time)', function () {
        $poll = plPoll(['A', 'B'], [], 'published', ['deadline_at' => now()->addHour()]);
        $this->travel(61)->minutes();

        plVote($this->student, $poll, [plOption($poll, 'A')->id])
            ->assertSessionHasErrors(['options' => __('activities.poll.vote.refused.closed')]);

        expect(PollVote::query()->count())->toBe(0);
    });

    it('lets only enrolled students of the space vote', function (string $who) {
        $poll = plPoll(['A', 'B']);
        $user = match ($who) {
            'teacher' => $this->teacher,
            'other teacher' => $this->otherTeacher,
            'student not enrolled' => $this->outsider,
            'org admin' => $this->admin,
        };

        plVote($user, $poll, [plOption($poll, 'A')->id])->assertForbidden();

        expect(PollParticipation::query()->count())->toBe(0)
            ->and(PollVote::query()->count())->toBe(0);
    })->with(['teacher', 'other teacher', 'student not enrolled', 'org admin']);

    it('does not exist as a draft for students', function () {
        $poll = plPoll(['A', 'B'], [], 'draft');

        plVote($this->student, $poll, [plOption($poll, 'A')->id])->assertNotFound();
        $this->actingAs($this->student)->get(plUrl("polls/{$poll->activity_id}"))->assertNotFound();
    });

    it('does not open a poll of another organisation', function () {
        $poll = plPoll(['A', 'B']);
        $optionId = plOption($poll, 'A')->id;
        $globex = $this->createOrganization(['subdomain' => 'globex']);
        $stranger = $this->asTenant($globex, fn () => User::factory()->student()->create());

        $this->actingAs($stranger)->get($this->tenantUrl($globex, "polls/{$poll->activity_id}"))->assertNotFound();
        $this->actingAs($stranger)->post($this->tenantUrl($globex, "polls/{$poll->activity_id}/vote"), ['options' => [$optionId]])->assertNotFound();
        $this->actingAs($stranger)->post(plUrl("polls/{$poll->activity_id}/vote"), ['options' => [$optionId]]);
        $this->actingAsTenant($this->acme);

        expect(PollVote::query()->count())->toBe(0);
    });

    it('answers 404 for an activity that is no poll', function () {
        $quiz = Activity::factory()->for($this->space, 'courseSpace')->published()->create();

        $this->actingAs($this->student)->get(plUrl("polls/{$quiz->id}"))->assertNotFound();
    });
});

describe('results (SCOPE-013, SEC-009)', function () {
    // UC-013: Given a poll with results after closing, When a student has
    // voted and the poll is still open, Then results are not shown to them.
    it('hides results after closing from a student who voted while the poll is open', function () {
        $poll = plPoll(['A', 'B'], ['results_visibility' => PollResultsVisibility::AfterClose]);
        plVote($this->student, $poll, [plOption($poll, 'A')->id])->assertSessionHasNoErrors();

        $this->actingAs($this->student)->get(plUrl("polls/{$poll->activity_id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Activities/Poll/Show')
                ->where('hasVoted', true)
                ->where('results', null)
                ->where('can.vote', false));

        $this->actingAs($this->teacher)->post(plUrl("activities/{$poll->activity_id}/close"));

        $this->actingAs($this->student)->get(plUrl("polls/{$poll->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('results.options.0.votes', 1)
                ->where('results.participants', 1));
    });

    it('shows results after voting only once the student has voted', function () {
        $poll = plPoll(['A', 'B'], ['results_visibility' => PollResultsVisibility::AfterVote]);

        $this->actingAs($this->student)->get(plUrl("polls/{$poll->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasVoted', false)
                ->where('results', null)
                ->where('can.vote', true));

        plVote($this->student, $poll, [plOption($poll, 'B')->id]);

        $this->actingAs($this->student)->get(plUrl("polls/{$poll->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasVoted', true)
                ->where('results.options.1.votes', 1));
    });

    it('shows the results of a closed poll to its students', function () {
        $poll = plPoll(['A', 'B'], ['results_visibility' => PollResultsVisibility::AfterClose], 'closed');

        $this->actingAs($this->student)->get(plUrl("polls/{$poll->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('poll.is_open', false)
                ->where('can.vote', false)
                ->where('results.participants', 0));
    });

    // UC-013: Given 3 votes on option A and 1 on B, When results are shown,
    // Then A has 3 and B has 1 and no voter identity is shown to anyone,
    // including the teacher and org admin.
    it('shows the counts and no voter identity to anyone', function () {
        $poll = plPoll(['A', 'B'], ['results_visibility' => PollResultsVisibility::AfterVote]);
        $third = User::factory()->student()->create();
        Enrolment::factory()->for($this->space, 'courseSpace')->for($third, 'student')->create();
        $fourth = User::factory()->student()->create();
        Enrolment::factory()->for($this->space, 'courseSpace')->for($fourth, 'student')->create();

        plVote($this->student, $poll, [plOption($poll, 'A')->id])->assertSessionHasNoErrors();
        plVote($this->classmate, $poll, [plOption($poll, 'A')->id])->assertSessionHasNoErrors();
        plVote($third, $poll, [plOption($poll, 'A')->id])->assertSessionHasNoErrors();
        plVote($fourth, $poll, [plOption($poll, 'B')->id])->assertSessionHasNoErrors();

        $results = [
            'participants' => 4,
            'options' => [
                ['id' => plOption($poll, 'A')->id, 'label' => 'A', 'votes' => 3],
                ['id' => plOption($poll, 'B')->id, 'label' => 'B', 'votes' => 1],
            ],
        ];

        foreach ([$this->teacher, $this->student] as $viewer) {
            $response = $this->actingAs($viewer)->get(plUrl("polls/{$poll->activity_id}"))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('results', $results));

            plAssertNoVoterIdentity($response, $viewer);
        }

        // The org admin sees no individual activities (OWN-004), so no poll either.
        $this->actingAs($this->admin)->get(plUrl("polls/{$poll->activity_id}"))->assertForbidden();
        $this->actingAs($this->admin)->get(plUrl("activities/{$poll->activity_id}"))->assertForbidden();

        // The submissions overview lists nobody for a poll.
        $overview = $this->actingAs($this->teacher)->get(plUrl("activities/{$poll->activity_id}/submissions"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('submissions', []));
        plAssertNoVoterIdentity($overview);

        // The activity page offers the poll and no submissions list.
        plAssertNoVoterIdentity($this->actingAs($this->teacher)->get(plUrl("activities/{$poll->activity_id}"))->assertOk());
    });

    it('lets the owning teacher see the results at any time, and no other teacher', function () {
        $poll = plPoll(['A', 'B'], ['results_visibility' => PollResultsVisibility::AfterClose]);
        plVote($this->student, $poll, [plOption($poll, 'A')->id]);

        $this->actingAs($this->teacher)->get(plUrl("polls/{$poll->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('results.options.0.votes', 1)
                ->where('hasVoted', false)
                ->where('can.vote', false));

        $this->actingAs($this->otherTeacher)->get(plUrl("polls/{$poll->activity_id}"))->assertForbidden();
    });

    it('stores no user reference and no time on votes (DATA-006)', function () {
        expect(Schema::getColumnListing('poll_votes'))->toEqualCanonicalizing(['id', 'organization_id', 'poll_option_id'])
            ->and(Schema::getColumnListing('poll_participations'))->not->toContain('poll_option_id');

        $poll = plPoll(['A', 'B']);
        plVote($this->student, $poll, [plOption($poll, 'A')->id]);

        // A random (version 4) id, not a time-ordered one.
        expect(PollVote::query()->value('id'))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
    });
});

describe('OWN-011: poll participations', function () {
    // OWN-011 (ROLE-Student): a student sees only their own participation;
    // reading or changing another's is denied and nothing changes.
    it('lets a student see only their own participation', function () {
        $poll = plPoll(['A', 'B']);
        plVote($this->classmate, $poll, [plOption($poll, 'A')->id]);
        plVote($this->student, $poll, [plOption($poll, 'B')->id]);

        $own = PollParticipation::query()->where('student_id', $this->student->id)->firstOrFail();
        $classmates = PollParticipation::query()->where('student_id', $this->classmate->id)->firstOrFail();
        $gate = Gate::forUser($this->student);

        expect($gate->allows('view', $own))->toBeTrue()
            ->and($gate->allows('view', $classmates))->toBeFalse()
            ->and($gate->allows('viewAny', PollParticipation::class))->toBeFalse()
            ->and($gate->allows('update', $classmates))->toBeFalse()
            ->and($gate->allows('delete', $classmates))->toBeFalse()
            ->and($gate->allows('update', $own))->toBeFalse()
            ->and($gate->allows('delete', $own))->toBeFalse();

        // Another student's poll page says nothing about the student's vote.
        $outsiderPoll = plPoll(['X', 'Y']);
        plVote($this->classmate, $outsiderPoll, [plOption($outsiderPoll, 'X')->id]);

        $this->actingAs($this->student)->get(plUrl("polls/{$outsiderPoll->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page->where('hasVoted', false)->where('results', null));

        // Voting again cannot change a participation.
        plVote($this->student, $poll, [plOption($poll, 'A')->id])->assertSessionHasErrors('options');

        expect(PollParticipation::query()->count())->toBe(3)
            ->and(plTally($poll))->toBe(['A' => 1, 'B' => 1]);
    });

    // OWN-011 (ROLE-Teacher): a teacher sees only the counts and the number
    // of participants, never who voted or what anyone voted.
    it('never lets a teacher read or change a participation', function () {
        $poll = plPoll(['A', 'B']);
        plVote($this->student, $poll, [plOption($poll, 'A')->id]);
        $participation = PollParticipation::query()->firstOrFail();

        foreach ([$this->teacher, $this->otherTeacher, $this->admin] as $user) {
            $gate = Gate::forUser($user);

            expect($gate->allows('view', $participation))->toBeFalse()
                ->and($gate->allows('viewAny', PollParticipation::class))->toBeFalse()
                ->and($gate->allows('update', $participation))->toBeFalse()
                ->and($gate->allows('delete', $participation))->toBeFalse();
        }

        $response = $this->actingAs($this->teacher)->get(plUrl("polls/{$poll->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('results.participants', 1)
                ->missing('participants')
                ->missing('participations'));
        plAssertNoVoterIdentity($response);

        // A teacher cannot vote, so cannot add or change a participation.
        plVote($this->teacher, $poll, [plOption($poll, 'B')->id])->assertForbidden();

        expect(PollParticipation::query()->count())->toBe(1)
            ->and(plTally($poll))->toBe(['A' => 1, 'B' => 0]);
    });
});

describe('the activity page', function () {
    it('links students and the teacher to the poll', function () {
        $poll = plPoll(['A', 'B']);

        $this->actingAs($this->student)->get(plUrl("activities/{$poll->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page->where('activity.type', 'poll')->where('can.participate', true));
        $this->actingAs($this->teacher)->get(plUrl("activities/{$poll->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page->where('can.viewSubmissions', true));
    });
});

it('isolates polls, options, participations and votes per organisation', function () {
    $this->assertTenantIsolated(Poll::class, fn () => Poll::factory()->create());
    $this->assertTenantIsolated(PollOption::class, function () {
        $option = new PollOption(['label' => 'A', 'position' => 0]);
        $option->poll_id = Poll::factory()->create()->activity_id;
        $option->save();

        return $option;
    });
    $this->assertTenantIsolated(PollParticipation::class, function () {
        $participation = new PollParticipation;
        $participation->poll_id = Poll::factory()->create()->activity_id;
        $participation->student_id = User::factory()->student()->create()->id;
        $participation->save();

        return $participation;
    });
    $this->assertTenantIsolated(PollVote::class, function () {
        $option = new PollOption(['label' => 'A', 'position' => 0]);
        $option->poll_id = Poll::factory()->create()->activity_id;
        $option->save();

        $vote = new PollVote;
        $vote->poll_option_id = $option->id;
        $vote->save();

        return $vote;
    });
});

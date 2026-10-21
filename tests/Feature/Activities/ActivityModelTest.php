<?php

use App\Modules\Activities\Contracts\ActivityType;
use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\ActivityStatus;
use App\Modules\Activities\Exceptions\InvalidActivityTransition;
use App\Modules\Activities\Exceptions\UnknownActivityType;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Courses\Models\CourseSpace;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Activities\FakeQuizType;

/*
| ENT-Activity: lifecycle (draft -> published -> closed), tenant isolation
| (DATA-007, SEC-004) and the ActivityType registry (ARCH-004).
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);
});

/**
 * Creates an activity and walks it to the given status through the state model.
 */
function activityIn(ActivityStatus $status): Activity
{
    $activity = Activity::factory()->create();

    if ($status !== ActivityStatus::Draft) {
        $activity->transitionTo(ActivityStatus::Published);
    }

    if ($status === ActivityStatus::Closed) {
        $activity->transitionTo(ActivityStatus::Closed);
    }

    return $activity->fresh() ?? $activity;
}

/**
 * Every (from, to) pair the state model does not list.
 *
 * @return array<string, array{ActivityStatus, ActivityStatus}>
 */
function forbiddenActivityTransitions(): array
{
    $pairs = [];

    foreach (ActivityStatus::cases() as $from) {
        foreach (ActivityStatus::cases() as $to) {
            if (! in_array("{$from->value} -> {$to->value}", ['draft -> published', 'published -> closed'], true)) {
                $pairs["{$from->value} -> {$to->value}"] = [$from, $to];
            }
        }
    }

    return $pairs;
}

it('creates activities as drafts in the organisation of their course space', function () {
    $activity = Activity::factory()->create();

    expect($activity->status)->toBe(ActivityStatus::Draft)
        ->and($activity->organization_id)->toBe($this->acme->id)
        ->and($activity->is_group)->toBeFalse()
        ->and($activity->published_at)->toBeNull();
});

it('allows exactly the transitions of the state model', function () {
    $activity = Activity::factory()->create();

    $activity->transitionTo(ActivityStatus::Published);
    expect($activity->fresh()?->status)->toBe(ActivityStatus::Published)
        ->and($activity->fresh()?->published_at)->not->toBeNull();

    $activity->transitionTo(ActivityStatus::Closed);
    expect($activity->fresh()?->status)->toBe(ActivityStatus::Closed);
});

describe('ENT-Activity: Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged', function () {
    it('rejects it through transitionTo()', function (ActivityStatus $from, ActivityStatus $to) {
        $activity = activityIn($from);

        expect(fn () => $activity->transitionTo($to))->toThrow(InvalidActivityTransition::class);

        expect($activity->status)->toBe($from)
            ->and($activity->fresh()?->status)->toBe($from);
    })->with(forbiddenActivityTransitions());

    it('rejects it when the status is assigned directly', function (ActivityStatus $from, ActivityStatus $to) {
        $activity = activityIn($from);
        $activity->status = $to;

        if ($from === $to) {
            // Not a change of status at all: nothing to reject, nothing changes.
            $activity->save();
        } else {
            expect(fn () => $activity->save())->toThrow(InvalidActivityTransition::class);
        }

        expect($activity->status)->toBe($from)
            ->and($activity->fresh()?->status)->toBe($from);
    })->with(forbiddenActivityTransitions());

    it('rejects creating an activity in any status but draft', function (ActivityStatus $status) {
        $space = CourseSpace::factory()->create();

        $activity = new Activity(['title' => 'Quiz 1']);
        $activity->course_space_id = $space->id;
        $activity->type = ActivityKind::Quiz;
        $activity->status = $status;

        expect(fn () => $activity->save())->toThrow(InvalidActivityTransition::class)
            ->and(Activity::query()->count())->toBe(0);
    })->with([ActivityStatus::Published, ActivityStatus::Closed]);

    it('rejects an unknown status in the database', function () {
        $activity = Activity::factory()->create();

        // In a savepoint, so the failed statement does not abort the test's transaction.
        expect(fn () => DB::transaction(fn () => DB::table('activities')->where('id', $activity->id)->update(['status' => 'archived'])))
            ->toThrow(QueryException::class)
            ->and($activity->fresh()?->status)->toBe(ActivityStatus::Draft);
    });
});

it('isolates activities per organisation', function () {
    $this->assertTenantIsolated(Activity::class, fn () => Activity::factory()->create());
});

it('keeps an activity in the organisation of its course space', function () {
    $beta = $this->createOrganization(['subdomain' => 'beta']);
    $foreignSpace = $this->asTenant($beta, fn () => CourseSpace::factory()->create());

    $activity = new Activity(['title' => 'Quiz 1']);
    $activity->course_space_id = $foreignSpace->id;
    $activity->type = ActivityKind::Quiz;

    expect(fn () => $activity->save())->toThrow(QueryException::class);
});

it('stores deadlines and publication times in UTC', function () {
    $activity = Activity::factory()->create(['deadline_at' => now('Europe/Athens')->addDay()]);
    $activity->transitionTo(ActivityStatus::Published);

    $row = DB::selectOne("SELECT to_char(deadline_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS deadline FROM activities WHERE id = ?", [$activity->id]);

    expect($row->deadline)->toBe($activity->deadline_at?->utc()->format('Y-m-d H:i'));
});

describe('ARCH-004: ActivityType registry', function () {
    it('offers only registered types', function () {
        $types = app(ActivityTypes::class);

        expect($types->kinds())->not->toContain(ActivityKind::Poll)
            ->and(fn () => $types->get(ActivityKind::Poll))->toThrow(UnknownActivityType::class);

        $types->register(FakeQuizType::class);

        expect($types->has(ActivityKind::Quiz))->toBeTrue()
            ->and($types->get(ActivityKind::Quiz))->toBeInstanceOf(ActivityType::class)
            ->and($types->kinds())->toContain(ActivityKind::Quiz)
            ->and($types->for(Activity::factory()->create()))->toBeInstanceOf(FakeQuizType::class);
    });

    it('is one registry for the whole application', function () {
        expect(app(ActivityTypes::class))->toBe(app(ActivityTypes::class));
    });

    it('refuses an implementation that does not implement the contract', function () {
        expect(fn () => app(ActivityTypes::class)->register(stdClass::class))->toThrow(InvalidArgumentException::class);
    });
});

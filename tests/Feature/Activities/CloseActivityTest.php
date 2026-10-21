<?php

use App\Modules\Activities\Actions\CloseActivity;
use App\Modules\Activities\Enums\ActivityStatus;
use App\Modules\Activities\Events\ActivityClosed;
use App\Modules\Activities\Exceptions\InvalidActivityTransition;
use App\Modules\Activities\Models\Activity;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/*
| Closing an activity (published -> closed) dispatches ActivityClosed with
| ids only, after the close has committed (SCOPE-024: the type's module
| finishes open work, e.g. submits in-progress quiz attempts).
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
});

it('dispatches ActivityClosed with the organisation and activity ids when the teacher closes an activity', function () {
    Event::fake([ActivityClosed::class]);
    $activity = Activity::factory()->for($this->space, 'courseSpace')->published()->create();

    $this->actingAs($this->teacher)
        ->post($this->tenantUrl($this->acme, "activities/{$activity->id}/close"))
        ->assertRedirect();

    expect($activity->refresh()->status)->toBe(ActivityStatus::Closed);
    Event::assertDispatchedTimes(ActivityClosed::class, 1);
    Event::assertDispatched(ActivityClosed::class, fn (ActivityClosed $event): bool => $event->activityId === $activity->id
        && $event->organizationId === $this->acme->id);
});

it('does not dispatch ActivityClosed when the close is refused or rolled back', function () {
    $draft = Activity::factory()->for($this->space, 'courseSpace')->create();
    $published = Activity::factory()->for($this->space, 'courseSpace')->published()->create();
    $dispatched = [];
    Event::listen(ActivityClosed::class, function (ActivityClosed $event) use (&$dispatched): void {
        $dispatched[] = $event->activityId;
    });

    expect(fn () => app(CloseActivity::class)->handle($draft))->toThrow(InvalidActivityTransition::class);

    try {
        DB::transaction(function () use ($published): void {
            app(CloseActivity::class)->handle($published);

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect($dispatched)->toBe([])
        ->and($draft->refresh()->status)->toBe(ActivityStatus::Draft)
        ->and($published->refresh()->status)->toBe(ActivityStatus::Published);
});

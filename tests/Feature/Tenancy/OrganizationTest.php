<?php

use App\Modules\Tenancy\Enums\GradingScale;
use App\Modules\Tenancy\Enums\Locale;
use App\Modules\Tenancy\Enums\OrganizationStatus;
use App\Modules\Tenancy\Exceptions\InvalidOrganizationTransition;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('stores an organisation with a UUID key in its initial state', function () {
    $organization = Organization::factory()->create([
        'subdomain' => 'acme',
        'grading_scale' => GradingScale::PointsPassMark,
        'default_locale' => Locale::English,
        'leaderboard_enabled' => false,
    ])->refresh();

    expect(Str::isUuid($organization->id))->toBeTrue()
        ->and($organization->status)->toBe(OrganizationStatus::Active)
        ->and($organization->grading_scale)->toBe(GradingScale::PointsPassMark)
        ->and($organization->default_locale)->toBe(Locale::English)
        ->and($organization->leaderboard_enabled)->toBeFalse()
        ->and($organization->isActive())->toBeTrue();
});

it('stores subdomains in lower case and keeps them unique regardless of case', function () {
    Organization::factory()->create(['subdomain' => 'acme']);

    // In a savepoint, so the failed insert does not abort the test transaction.
    expect(fn () => DB::transaction(fn () => Organization::factory()->create(['subdomain' => 'ACME'])))
        ->toThrow(UniqueConstraintViolationException::class);

    expect(Organization::query()->where('subdomain', 'acme')->count())->toBe(1);
});

it('allows the transitions of the state model', function (OrganizationStatus $from, OrganizationStatus $to) {
    $organization = Organization::factory()->create();
    if ($from !== OrganizationStatus::initial()) {
        $organization->transitionTo($from);
    }

    $organization->transitionTo($to);

    expect($organization->status)->toBe($to)
        ->and($organization->fresh()?->status)->toBe($to);
})->with([
    'active -> suspended' => [OrganizationStatus::Active, OrganizationStatus::Suspended],
    'suspended -> active' => [OrganizationStatus::Suspended, OrganizationStatus::Active],
]);

/*
| ENT-Organization: Given any state, When a transition not in the state model
| is attempted, Then it is rejected and the state is unchanged.
*/
$invalidTransitions = function (): array {
    $cases = [];
    foreach (OrganizationStatus::cases() as $from) {
        foreach (OrganizationStatus::cases() as $to) {
            if (! $from->canTransitionTo($to)) {
                $cases["{$from->value} -> {$to->value}"] = [$from, $to];
            }
        }
    }

    return $cases;
};

it('rejects a transition outside the state model and leaves the state unchanged', function (OrganizationStatus $from, OrganizationStatus $to) {
    $organization = Organization::factory()->create();
    if ($from !== OrganizationStatus::initial()) {
        $organization->transitionTo($from);
    }

    expect(fn () => $organization->transitionTo($to))->toThrow(InvalidOrganizationTransition::class);

    expect($organization->status)->toBe($from)
        ->and($organization->fresh()?->status)->toBe($from);
})->with($invalidTransitions);

it('rejects a transition outside the state model made by assigning the status directly', function (OrganizationStatus $from, OrganizationStatus $to) {
    $organization = Organization::factory()->create();
    if ($from !== OrganizationStatus::initial()) {
        $organization->transitionTo($from);
    }

    $organization->status = $to;

    if ($organization->isDirty('status')) {
        expect(fn () => $organization->save())->toThrow(InvalidOrganizationTransition::class);
    } else {
        // Re-saving an unchanged status writes nothing.
        $organization->save();
    }

    expect($organization->status)->toBe($from)
        ->and($organization->fresh()?->status)->toBe($from);
})->with($invalidTransitions);

it('rejects creating an organisation in a state other than the initial one', function () {
    $organization = Organization::factory()->make();
    $organization->status = OrganizationStatus::Suspended;

    expect(fn () => $organization->save())->toThrow(InvalidOrganizationTransition::class);
    expect(Organization::query()->count())->toBe(0);
});

it('does not let status be mass assigned', function () {
    $organization = Organization::factory()->create();
    $organization->fill(['status' => 'suspended'])->save();

    expect($organization->fresh()?->status)->toBe(OrganizationStatus::Active);
});

it('rejects unknown enum values at the database level', function (string $column, string $value) {
    $organization = Organization::factory()->create();

    expect(fn () => DB::table('organizations')->where('id', $organization->id)->update([$column => $value]))
        ->toThrow(QueryException::class);
})->with([
    ['status', 'deleted'],
    ['grading_scale', 'letters'],
    ['default_locale', 'de'],
    ['subdomain', 'ACME'],
]);

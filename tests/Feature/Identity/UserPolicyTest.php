<?php

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

/*
| OWN-001 — A student or teacher reads and edits only their own user record;
| an org admin manages only users of their own organisation.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->beta = $this->createOrganization(['subdomain' => 'beta']);

    $acmeUsers = User::factory()->for($this->acme, 'organization');
    $this->student = $acmeUsers->student()->create();
    $this->otherStudent = $acmeUsers->student()->create();
    $this->teacher = $acmeUsers->teacher()->create();
    $this->admin = $acmeUsers->orgAdmin()->create();

    $this->betaUser = User::factory()->for($this->beta, 'organization')->student()->create();
});

/**
 * @return array<string, mixed>
 */
function snapshot(User $user): array
{
    return User::query()->withoutGlobalScopes()->findOrFail($user->getKey())->getAttributes();
}

it('lets every role read and edit their own record', function (string $actor) {
    $user = $this->{$actor};

    expect(Gate::forUser($user)->allows('view', $user))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $user))->toBeTrue();
})->with(['student', 'teacher', 'admin']);

it('denies a student any other user record and changes nothing', function (string $target) {
    $record = $this->{$target};
    $before = snapshot($record);

    foreach (['view', 'update', 'delete'] as $ability) {
        expect(Gate::forUser($this->student)->allows($ability, $record))->toBeFalse("student may {$ability} {$target}");
    }

    expect(snapshot($record))->toBe($before);
})->with(['otherStudent', 'teacher', 'admin', 'betaUser']);

it('denies a teacher any other user record and changes nothing', function (string $target) {
    $record = $this->{$target};
    $before = snapshot($record);

    foreach (['view', 'update', 'delete'] as $ability) {
        expect(Gate::forUser($this->teacher)->allows($ability, $record))->toBeFalse("teacher may {$ability} {$target}");
    }

    expect(snapshot($record))->toBe($before);
})->with(['student', 'otherStudent', 'admin', 'betaUser']);

it('lets an org admin manage users of their own organisation', function (string $target) {
    foreach (['view', 'update', 'delete'] as $ability) {
        expect(Gate::forUser($this->admin)->allows($ability, $this->{$target}))->toBeTrue();
    }
})->with(['student', 'teacher']);

it('denies an org admin users of another organisation and changes nothing', function () {
    $before = snapshot($this->betaUser);

    foreach (['view', 'update', 'delete'] as $ability) {
        expect(Gate::forUser($this->admin)->allows($ability, $this->betaUser))->toBeFalse("admin may {$ability} a beta user");
    }

    // Inside acme, beta's users are not even visible.
    $this->asTenant($this->acme, fn () => expect(User::query()->find($this->betaUser->getKey()))->toBeNull());

    expect(snapshot($this->betaUser))->toBe($before);
});

it('denies everything to a deactivated actor', function () {
    $this->admin->forceFill(['is_active' => false])->save();

    expect(Gate::forUser($this->admin)->allows('view', $this->admin))->toBeFalse()
        ->and(Gate::forUser($this->admin)->allows('update', $this->student))->toBeFalse();
});

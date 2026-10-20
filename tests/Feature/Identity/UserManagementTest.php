<?php

use App\Modules\Identity\Database\Factories\InvitationFactory;
use App\Modules\Identity\Enums\InvitationStatus;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Events\UserDeactivated;
use App\Modules\Identity\Events\UserReactivated;
use App\Modules\Identity\Events\UserRoleChanged;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Support\OrganizationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-032 — Manage users: list (search, filter, paginate), change roles,
| deactivate and reactivate; OWN-001 and OWN-002 over HTTP.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->beta = $this->createOrganization(['subdomain' => 'beta']);

    $acmeUsers = User::factory()->for($this->acme, 'organization');
    $this->admin = $acmeUsers->orgAdmin()->create(['name' => 'Alice Admin', 'email' => 'alice@acme.test']);
    $this->teacher = $acmeUsers->teacher()->create(['name' => 'Tom Teacher', 'email' => 'tom@acme.test']);
    $this->student = $acmeUsers->student()->create(['name' => 'Sam Student', 'email' => 'sam@acme.test']);

    $betaUsers = User::factory()->for($this->beta, 'organization');
    $this->betaAdmin = $betaUsers->orgAdmin()->create(['name' => 'Bob Beta', 'email' => 'bob@beta.test']);
    $this->betaUser = $betaUsers->student()->create(['name' => 'Beth Beta', 'email' => 'beth@beta.test']);
});

/**
 * @return array<string, mixed>
 */
function userRow(User $user): array
{
    return User::query()->withoutGlobalScopes()->findOrFail($user->getKey())->getAttributes();
}

/**
 * @return array<string, mixed>
 */
function invitationSnapshot(Invitation $invitation): array
{
    return Invitation::query()->withoutGlobalScope(OrganizationScope::class)->findOrFail($invitation->getKey())->getAttributes();
}

describe('user list', function () {
    it('lists only users of the admin\'s own organisation', function () {
        $this->actingAs($this->admin)
            ->get($this->tenantUrl($this->acme, 'users'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Identity/Users')
                ->where('users.total', 3)
                ->where('users.data', fn ($users) => collect($users)->pluck('email')->sort()->values()->all() === ['alice@acme.test', 'sam@acme.test', 'tom@acme.test']));
    });

    it('never shows users of another organisation, whatever the filters', function (array $query) {
        $this->actingAs($this->admin)
            ->get($this->tenantUrl($this->acme, 'users').'?'.http_build_query($query))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.data', fn ($users) => collect($users)->pluck('id')->intersect([$this->betaAdmin->getKey(), $this->betaUser->getKey()])->isEmpty()));
    })->with([
        'no filter' => [[]],
        'search beta' => [['search' => 'beta']],
        'search bob' => [['search' => 'bob']],
        'students' => [['role' => 'student']],
        'admins' => [['role' => 'org_admin']],
    ]);

    it('searches by name, nickname or email, case-insensitively', function () {
        $this->actingAs($this->admin)
            ->get($this->tenantUrl($this->acme, 'users?search=TOM'))
            ->assertInertia(fn (Assert $page) => $page->where('users.total', 1)->where('users.data.0.email', 'tom@acme.test'));

        $this->get($this->tenantUrl($this->acme, 'users?search=sam@acme'))
            ->assertInertia(fn (Assert $page) => $page->where('users.total', 1)->where('users.data.0.email', 'sam@acme.test'));

        $nickname = $this->teacher->fresh()->nickname;
        $this->get($this->tenantUrl($this->acme, 'users?search='.urlencode($nickname)))
            ->assertInertia(fn (Assert $page) => $page->where('users.data', fn ($users) => collect($users)->pluck('email')->contains('tom@acme.test')));
    });

    it('filters by role and status', function () {
        $this->student->forceFill(['is_active' => false])->save();

        $this->actingAs($this->admin)
            ->get($this->tenantUrl($this->acme, 'users?role=teacher'))
            ->assertInertia(fn (Assert $page) => $page->where('users.total', 1)->where('users.data.0.email', 'tom@acme.test'));

        $this->get($this->tenantUrl($this->acme, 'users?status=inactive'))
            ->assertInertia(fn (Assert $page) => $page->where('users.total', 1)->where('users.data.0.email', 'sam@acme.test'));

        $this->get($this->tenantUrl($this->acme, 'users?status=active'))
            ->assertInertia(fn (Assert $page) => $page->where('users.total', 2));
    });

    it('paginates the list', function () {
        User::factory()->for($this->acme, 'organization')->count(30)->create();

        $this->actingAs($this->admin)
            ->get($this->tenantUrl($this->acme, 'users?page=2'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.total', 33)
                ->where('users.current_page', 2)
                ->has('users.data', 8));
    });

    it('shows only pending invitations of the admin\'s own organisation (OWN-002)', function () {
        $own = Invitation::factory()->for($this->acme, 'organization')->create(['email' => 'own@example.com']);
        Invitation::factory()->for($this->acme, 'organization')->inStatus(InvitationStatus::Revoked)->create();
        Invitation::factory()->for($this->beta, 'organization')->create(['email' => 'foreign@example.com']);

        $this->actingAs($this->admin)
            ->get($this->tenantUrl($this->acme, 'users'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('invitations', 1)
                ->where('invitations.0.id', $own->getKey())
                ->where('invitations.0.email', 'own@example.com'));
    });

    it('does not expose password hashes, dates of birth or minor flags', function () {
        $this->actingAs($this->admin)
            ->get($this->tenantUrl($this->acme, 'users'))
            ->assertInertia(fn (Assert $page) => $page->where('users.data.0', fn ($user) => array_keys(collect($user)->all()) === ['id', 'name', 'nickname', 'email', 'role', 'is_active']));
    });
});

describe('roles and activation', function () {
    it('changes a user\'s role and dispatches UserRoleChanged', function () {
        Event::fake([UserRoleChanged::class]);

        $this->actingAs($this->admin)
            ->patch($this->tenantUrl($this->acme, "users/{$this->student->getKey()}/role"), ['role' => 'teacher'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($this->student->fresh()->role)->toBe(Role::Teacher);
        Event::assertDispatched(UserRoleChanged::class, fn (UserRoleChanged $event) => $event->userId === $this->student->getKey()
            && $event->actorId === $this->admin->getKey()
            && $event->fromRole === 'student'
            && $event->toRole === 'teacher');
    });

    it('rejects an admin demoting themselves when they are the only active org admin', function () {
        Event::fake([UserRoleChanged::class]);

        $this->actingAs($this->admin)
            ->patch($this->tenantUrl($this->acme, "users/{$this->admin->getKey()}/role"), ['role' => 'teacher'])
            ->assertSessionHasErrors('role');

        expect($this->admin->fresh()->role)->toBe(Role::OrgAdmin);
        Event::assertNotDispatched(UserRoleChanged::class);
    });

    it('rejects an admin deactivating themselves when they are the only active org admin', function () {
        Event::fake([UserDeactivated::class]);

        $this->actingAs($this->admin)
            ->post($this->tenantUrl($this->acme, "users/{$this->admin->getKey()}/deactivate"))
            ->assertSessionHasErrors('user');

        expect($this->admin->fresh()->is_active)->toBeTrue();
        Event::assertNotDispatched(UserDeactivated::class);
    });

    it('counts only active org admins: a deactivated second admin does not help', function () {
        User::factory()->for($this->acme, 'organization')->orgAdmin()->inactive()->create();

        $this->actingAs($this->admin)
            ->post($this->tenantUrl($this->acme, "users/{$this->admin->getKey()}/deactivate"))
            ->assertSessionHasErrors('user');

        expect($this->admin->fresh()->is_active)->toBeTrue();
    });

    it('does not count an org admin of another organisation', function () {
        // beta has its own admin; acme's only admin still cannot leave.
        $this->actingAs($this->admin)
            ->patch($this->tenantUrl($this->acme, "users/{$this->admin->getKey()}/role"), ['role' => 'student'])
            ->assertSessionHasErrors('role');
    });

    it('lets an admin step down when another active org admin exists', function () {
        User::factory()->for($this->acme, 'organization')->orgAdmin()->create();

        $this->actingAs($this->admin)
            ->patch($this->tenantUrl($this->acme, "users/{$this->admin->getKey()}/role"), ['role' => 'teacher'])
            ->assertSessionHasNoErrors();

        expect($this->admin->fresh()->role)->toBe(Role::Teacher);
    });

    it('deactivates and reactivates a user', function () {
        Event::fake([UserDeactivated::class, UserReactivated::class]);

        $this->actingAs($this->admin)
            ->post($this->tenantUrl($this->acme, "users/{$this->teacher->getKey()}/deactivate"))
            ->assertSessionHasNoErrors();

        expect($this->teacher->fresh()->is_active)->toBeFalse();
        Event::assertDispatched(UserDeactivated::class, fn (UserDeactivated $event) => $event->userId === $this->teacher->getKey()
            && $event->actorId === $this->admin->getKey());

        $this->post($this->tenantUrl($this->acme, "users/{$this->teacher->getKey()}/reactivate"))
            ->assertSessionHasNoErrors();

        expect($this->teacher->fresh()->is_active)->toBeTrue();
        Event::assertDispatched(UserReactivated::class);
    });

    it('logs a deactivated user with an open session out on their next request', function () {
        $this->actingAs($this->teacher)->get($this->tenantUrl($this->acme, 'dashboard'))->assertOk();

        $this->actingAs($this->admin)
            ->post($this->tenantUrl($this->acme, "users/{$this->teacher->getKey()}/deactivate"))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->teacher->fresh())
            ->get($this->tenantUrl($this->acme, 'dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    });
});

describe('OWN-001 over HTTP', function () {
    it('denies a student or teacher the user list and every management action, and changes nothing', function (string $actor) {
        $actor = $this->{$actor};
        $targets = [$this->student, $this->teacher, $this->admin, $this->betaUser];
        $before = array_map(fn (User $user) => userRow($user), $targets);

        $this->actingAs($actor)->get($this->tenantUrl($this->acme, 'users'))->assertForbidden();

        foreach ($targets as $target) {
            $status = $target->organization_id === $this->acme->getKey() ? 403 : 404;
            $id = $target->getKey();

            $this->patch($this->tenantUrl($this->acme, "users/{$id}/role"), ['role' => 'org_admin'])->assertStatus($status);
            $this->post($this->tenantUrl($this->acme, "users/{$id}/deactivate"))->assertStatus($status);
            $this->post($this->tenantUrl($this->acme, "users/{$id}/reactivate"))->assertStatus($status);
        }

        expect(array_map(fn (User $user) => userRow($user), $targets))->toBe($before);
    })->with(['student', 'teacher']);

    it('does not let a student promote themselves', function () {
        $this->actingAs($this->student)
            ->patch($this->tenantUrl($this->acme, "users/{$this->student->getKey()}/role"), ['role' => 'org_admin'])
            ->assertForbidden();

        expect($this->student->fresh()->role)->toBe(Role::Student);
    });

    it('denies an org admin users of another organisation and changes nothing', function () {
        $before = userRow($this->betaUser);
        $id = $this->betaUser->getKey();

        $this->actingAs($this->admin);
        $this->patch($this->tenantUrl($this->acme, "users/{$id}/role"), ['role' => 'teacher'])->assertNotFound();
        $this->post($this->tenantUrl($this->acme, "users/{$id}/deactivate"))->assertNotFound();
        $this->post($this->tenantUrl($this->acme, "users/{$id}/reactivate"))->assertNotFound();

        // Even on beta's subdomain, the policy denies acme's admin.
        $this->post($this->tenantUrl($this->beta, "users/{$id}/deactivate"))->assertForbidden();

        expect(userRow($this->betaUser))->toBe($before);
    });
});

describe('OWN-002 over HTTP', function () {
    it('denies an org admin invitations of another organisation and changes nothing', function () {
        $foreign = Invitation::factory()->for($this->beta, 'organization')->create();
        $before = invitationSnapshot($foreign);

        $this->actingAs($this->admin)
            ->post($this->tenantUrl($this->acme, "invitations/{$foreign->getKey()}/revoke"))
            ->assertNotFound();

        expect(invitationSnapshot($foreign))->toBe($before);
    });

    it('denies students and teachers any invitation and changes nothing', function (string $actor) {
        $own = Invitation::factory()->for($this->acme, 'organization')->create();
        $before = invitationSnapshot($own);

        $this->actingAs($this->{$actor})
            ->post($this->tenantUrl($this->acme, "invitations/{$own->getKey()}/revoke"))
            ->assertForbidden();

        expect(invitationSnapshot($own))->toBe($before);
    })->with(['student', 'teacher']);

    it('applies the policy to invitations of another organisation', function () {
        $foreign = Invitation::factory()->for($this->beta, 'organization')->create();
        $own = Invitation::factory()->for($this->acme, 'organization')->create();

        expect($this->admin->can('view', $foreign))->toBeFalse()
            ->and($this->admin->can('revoke', $foreign))->toBeFalse()
            ->and($this->admin->can('view', $own))->toBeTrue()
            ->and($this->admin->can('revoke', $own))->toBeTrue()
            ->and($this->teacher->can('view', $own))->toBeFalse()
            ->and($this->student->can('revoke', $own))->toBeFalse();

        $this->asTenant($this->acme, fn () => expect(Invitation::query()->find($foreign->getKey()))->toBeNull());
    });

    it('makes factory tokens available for tests', function () {
        Invitation::factory()->for($this->acme, 'organization')->create();

        expect(InvitationFactory::$lastToken)->toBeString()->toHaveLength(48);
    });
});

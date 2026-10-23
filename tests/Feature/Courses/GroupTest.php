<?php

use App\Modules\Courses\Actions\CreateGroup;
use App\Modules\Courses\Actions\InviteToGroup;
use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Events\GroupInvitationCreated;
use App\Modules\Courses\Events\GroupMembershipChanged;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-019 and SCOPE-025: groups of a course space. Students create groups,
| invite, accept, decline, withdraw, leave, rename, transfer and dissolve;
| unenrolment and archiving end memberships; the owning teacher and org
| admins read groups only.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->admin = User::factory()->orgAdmin()->create();

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);

    [$this->owner, $this->alice, $this->bob, $this->xena, $this->yuri] = User::factory()->student()->count(5)->create()->all();
    $this->enrolments = [];
    foreach ([$this->owner, $this->alice, $this->bob, $this->xena, $this->yuri] as $student) {
        $this->enrolments[$student->id] = Enrolment::factory()->for($this->space, 'courseSpace')->for($student, 'student')->create();
    }

    // Not enrolled in the space.
    $this->outsider = User::factory()->student()->create();
});

function groupsUrl(string $path = ''): string
{
    $space = test()->space;

    return test()->tenantUrl(test()->acme, rtrim("courses/{$space->id}/groups/{$path}", '/'));
}

function groupUrl(Group $group, string $path = ''): string
{
    return groupsUrl(rtrim("{$group->id}/{$path}", '/'));
}

function createGroupAs(User $student, string $name = 'Team'): Group
{
    test()->actingAs($student)->post(groupsUrl(), ['name' => $name])->assertSessionHasNoErrors();

    return Group::query()->where('name', $name)->latest()->firstOrFail();
}

function inviteAs(User $member, Group $group, User $student): GroupMembership
{
    test()->actingAs($member)
        ->post(groupUrl($group, 'invitations'), ['student_id' => $student->id])
        ->assertSessionHasNoErrors();

    return GroupMembership::query()
        ->where('group_id', $group->id)
        ->where('student_id', $student->id)
        ->where('status', GroupMembershipStatus::Invited)
        ->firstOrFail();
}

function acceptAs(User $student, Group $group, GroupMembership $invitation): void
{
    test()->actingAs($student)
        ->post(groupUrl($group, "invitations/{$invitation->id}/accept"))
        ->assertSessionHasNoErrors();
}

/**
 * A group of the owner with alice and bob as members (alice joined first).
 */
function groupWithMembers(): Group
{
    $group = createGroupAs(test()->owner);

    test()->travel(1)->minutes();
    acceptAs(test()->alice, $group, inviteAs(test()->owner, $group, test()->alice));
    test()->travel(1)->minutes();
    acceptAs(test()->bob, $group, inviteAs(test()->owner, $group, test()->bob));

    return $group;
}

function rowOf(Group $group, User $student): GroupMembership
{
    return GroupMembership::query()
        ->where('group_id', $group->id)
        ->where('student_id', $student->id)
        ->latest()
        ->firstOrFail();
}

describe('creating', function () {
    it('makes the enrolled creator owner and first member with joined_at', function () {
        $group = createGroupAs($this->owner, 'Team Rocket');

        $row = rowOf($group, $this->owner);

        expect($group->owner_id)->toBe($this->owner->id)
            ->and($group->created_by)->toBe($this->owner->id)
            ->and($group->course_space_id)->toBe($this->space->id)
            ->and($group->organization_id)->toBe($this->acme->id)
            ->and($row->status)->toBe(GroupMembershipStatus::Member)
            ->and($row->joined_at)->not->toBeNull()
            ->and($group->memberships()->count())->toBe(1);
    });

    it('lets a member of a group create a second group and accept a third one in the same space', function () {
        $first = createGroupAs($this->alice, 'First');
        $second = createGroupAs($this->alice, 'Second');

        $third = createGroupAs($this->bob, 'Third');
        acceptAs($this->alice, $third, inviteAs($this->bob, $third, $this->alice));

        foreach ([$first, $second, $third] as $group) {
            expect(rowOf($group, $this->alice)->status)->toBe(GroupMembershipStatus::Member);
        }
    });

    it('refuses a student who is not enrolled in the space', function () {
        $this->actingAs($this->outsider)->post(groupsUrl(), ['name' => 'Nope'])->assertForbidden();

        expect(Group::query()->count())->toBe(0);
    });

    it('requires a name', function () {
        $this->actingAs($this->owner)->post(groupsUrl(), ['name' => ''])->assertSessionHasErrors('name');

        expect(Group::query()->count())->toBe(0);
    });
});

describe('inviting', function () {
    it('creates an invited row and dispatches one GroupInvitationCreated', function () {
        Event::fake([GroupInvitationCreated::class]);
        $group = createGroupAs($this->owner);

        $invitation = inviteAs($this->owner, $group, $this->xena);

        expect($invitation->status)->toBe(GroupMembershipStatus::Invited)
            ->and($invitation->invited_by)->toBe($this->owner->id)
            ->and($invitation->joined_at)->toBeNull();

        Event::assertDispatchedTimes(GroupInvitationCreated::class, 1);
        Event::assertDispatched(GroupInvitationCreated::class, fn (GroupInvitationCreated $event): bool => $event->organizationId === $this->acme->id
            && $event->groupMembershipId === $invitation->id);
    });

    it('dispatches GroupInvitationCreated only after commit', function () {
        $group = createGroupAs($this->owner);
        $seen = [];
        Event::listen(GroupInvitationCreated::class, function (GroupInvitationCreated $event) use (&$seen): void {
            $seen[] = $event->groupMembershipId;
        });

        try {
            DB::transaction(function () use ($group): void {
                app(InviteToGroup::class)->handle($this->owner, $group, $this->xena->id);

                throw new RuntimeException('abort');
            });
        } catch (RuntimeException) {
        }

        expect($seen)->toBe([])
            ->and($group->memberships()->where('student_id', $this->xena->id)->exists())->toBeFalse();

        $invitation = app(InviteToGroup::class)->handle($this->owner, $group, $this->xena->id);

        expect($seen)->toBe([$invitation->id]);
    });

    it('lets any member invite, not only the owner', function () {
        $group = groupWithMembers();

        $invitation = inviteAs($this->bob, $group, $this->xena);

        expect($invitation->invited_by)->toBe($this->bob->id);
    });

    it('refuses to invite a student not enrolled in the space', function () {
        Event::fake([GroupInvitationCreated::class]);
        $group = createGroupAs($this->owner);

        $this->actingAs($this->owner)
            ->post(groupUrl($group, 'invitations'), ['student_id' => $this->outsider->id])
            ->assertSessionHasErrors('student_id');

        expect($group->memberships()->where('student_id', $this->outsider->id)->exists())->toBeFalse();
        Event::assertNotDispatched(GroupInvitationCreated::class);
    });

    it('refuses to invite a student who is already invited or a member', function () {
        $group = createGroupAs($this->owner);
        inviteAs($this->owner, $group, $this->xena);

        foreach ([$this->xena, $this->owner] as $student) {
            $this->actingAs($this->owner)
                ->post(groupUrl($group, 'invitations'), ['student_id' => $student->id])
                ->assertSessionHasErrors('student_id');
        }

        expect($group->memberships()->count())->toBe(2);
    });

    it('keeps at most one invited or member row per student with a partial unique index', function () {
        $group = createGroupAs($this->owner);
        inviteAs($this->owner, $group, $this->xena);

        expect(fn () => DB::table('group_memberships')->insert([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->acme->id,
            'group_id' => $group->id,
            'student_id' => $this->xena->id,
            'status' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(UniqueConstraintViolationException::class);
    });

    it('creates a new invited row after declined, left or withdrawn and leaves the old row unchanged', function () {
        $group = createGroupAs($this->owner);

        // declined
        $declined = inviteAs($this->owner, $group, $this->xena);
        $this->actingAs($this->xena)->post(groupUrl($group, "invitations/{$declined->id}/decline"))->assertSessionHasNoErrors();

        // withdrawn
        $withdrawn = inviteAs($this->owner, $group, $this->yuri);
        $this->actingAs($this->owner)->delete(groupUrl($group, "invitations/{$withdrawn->id}"))->assertSessionHasNoErrors();

        // left
        $joined = inviteAs($this->owner, $group, $this->alice);
        acceptAs($this->alice, $group, $joined);
        $this->actingAs($this->alice)->post(groupUrl($group, 'leave'))->assertSessionHasNoErrors();

        $before = GroupMembership::query()->whereKey([$declined->id, $withdrawn->id, $joined->id])->get()->keyBy('id')->map->getAttributes()->all();

        $this->travel(1)->minutes();
        foreach ([$this->xena, $this->yuri, $this->alice] as $student) {
            $new = inviteAs($this->owner, $group, $student);

            expect($new->id)->not->toBeIn([$declined->id, $withdrawn->id, $joined->id]);
        }

        $after = GroupMembership::query()->whereKey(array_keys($before))->get()->keyBy('id')->map->getAttributes()->all();

        expect($after)->toBe($before)
            ->and($declined->fresh()?->status)->toBe(GroupMembershipStatus::Declined)
            ->and($withdrawn->fresh()?->status)->toBe(GroupMembershipStatus::Withdrawn)
            ->and($joined->fresh()?->status)->toBe(GroupMembershipStatus::Left)
            ->and($group->memberships()->count())->toBe(7);
    });

    it('offers in the invite dialog only enrolled students who are neither members of nor invited to the group', function () {
        $group = createGroupAs($this->owner);
        inviteAs($this->owner, $group, $this->xena);
        $declined = inviteAs($this->owner, $group, $this->yuri);
        $this->actingAs($this->yuri)->post(groupUrl($group, "invitations/{$declined->id}/decline"));

        $this->actingAs($this->owner)->get(groupUrl($group))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Courses/Groups/Show')
                ->where('invitableStudents', fn ($students) => collect($students)->pluck('id')->sort()->values()->all()
                    === collect([$this->alice->id, $this->bob->id, $this->yuri->id])->sort()->values()->all()));
    });
});

describe('answering an invitation', function () {
    it('makes the invitee a member with joined_at and dispatches GroupMembershipChanged(invited, member)', function () {
        $group = createGroupAs($this->owner);
        $invitation = inviteAs($this->owner, $group, $this->xena);
        Event::fake([GroupMembershipChanged::class]);

        acceptAs($this->xena, $group, $invitation);

        $invitation->refresh();
        expect($invitation->status)->toBe(GroupMembershipStatus::Member)
            ->and($invitation->joined_at)->not->toBeNull();

        Event::assertDispatchedTimes(GroupMembershipChanged::class, 1);
        Event::assertDispatched(GroupMembershipChanged::class, fn (GroupMembershipChanged $event): bool => $event->organizationId === $this->acme->id
            && $event->groupMembershipId === $invitation->id
            && $event->fromStatus === 'invited'
            && $event->toStatus === 'member');
    });

    it('declines an invitation', function () {
        $group = createGroupAs($this->owner);
        $invitation = inviteAs($this->owner, $group, $this->xena);
        Event::fake([GroupMembershipChanged::class]);

        $this->actingAs($this->xena)->post(groupUrl($group, "invitations/{$invitation->id}/decline"))->assertSessionHasNoErrors();

        expect($invitation->refresh()->status)->toBe(GroupMembershipStatus::Declined)
            ->and($invitation->joined_at)->toBeNull();
        Event::assertDispatched(GroupMembershipChanged::class, fn (GroupMembershipChanged $event): bool => $event->fromStatus === 'invited' && $event->toStatus === 'declined');
    });

    it('lets only the invitee answer', function () {
        $group = createGroupAs($this->owner);
        $invitation = inviteAs($this->owner, $group, $this->xena);

        foreach ([$this->owner, $this->yuri, $this->teacher, $this->admin] as $user) {
            $this->actingAs($user)->post(groupUrl($group, "invitations/{$invitation->id}/accept"))->assertForbidden();
            $this->actingAs($user)->post(groupUrl($group, "invitations/{$invitation->id}/decline"))->assertForbidden();
        }

        expect($invitation->refresh()->status)->toBe(GroupMembershipStatus::Invited);
    });

    it('refuses to accept once the invitee is no longer enrolled', function () {
        $group = createGroupAs($this->owner);
        $invitation = inviteAs($this->owner, $group, $this->xena);

        // Unenrolment withdraws the invitation; the former invitee cannot accept it.
        $this->actingAs($this->teacher)
            ->delete($this->tenantUrl($this->acme, "courses/{$this->space->id}/students/{$this->enrolments[$this->xena->id]->id}"))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->xena)->post(groupUrl($group, "invitations/{$invitation->id}/accept"))->assertForbidden();

        expect($invitation->refresh()->status)->toBe(GroupMembershipStatus::Withdrawn);
    });
});

describe('withdrawing', function () {
    it('lets any member withdraw a pending invitation, after which it cannot be accepted', function () {
        $group = groupWithMembers();
        $invitation = inviteAs($this->owner, $group, $this->xena);
        Event::fake([GroupMembershipChanged::class]);

        $this->actingAs($this->bob)->delete(groupUrl($group, "invitations/{$invitation->id}"))->assertSessionHasNoErrors();

        expect($invitation->refresh()->status)->toBe(GroupMembershipStatus::Withdrawn);
        Event::assertDispatched(GroupMembershipChanged::class, fn (GroupMembershipChanged $event): bool => $event->fromStatus === 'invited' && $event->toStatus === 'withdrawn');

        $this->actingAs($this->xena)
            ->post(groupUrl($group, "invitations/{$invitation->id}/accept"))
            ->assertSessionHasErrors('invitation');

        expect($invitation->refresh()->status)->toBe(GroupMembershipStatus::Withdrawn)
            ->and($group->hasMember($this->xena))->toBeFalse();
    });

    it('refuses withdrawal by the invitee or a non-member', function () {
        $group = createGroupAs($this->owner);
        $invitation = inviteAs($this->owner, $group, $this->xena);

        foreach ([$this->xena, $this->yuri, $this->teacher, $this->admin] as $user) {
            $this->actingAs($user)->delete(groupUrl($group, "invitations/{$invitation->id}"))->assertForbidden();
        }

        expect($invitation->refresh()->status)->toBe(GroupMembershipStatus::Invited);
    });
});

describe('leaving', function () {
    it('sets a leaving member to left with left_at', function () {
        $group = groupWithMembers();
        Event::fake([GroupMembershipChanged::class]);

        $this->actingAs($this->bob)->post(groupUrl($group, 'leave'))->assertSessionHasNoErrors();

        $row = rowOf($group, $this->bob);
        expect($row->status)->toBe(GroupMembershipStatus::Left)
            ->and($row->left_at)->not->toBeNull()
            ->and($group->fresh())->not->toBeNull();
        Event::assertDispatched(GroupMembershipChanged::class, fn (GroupMembershipChanged $event): bool => $event->groupMembershipId === $row->id
            && $event->fromStatus === 'member' && $event->toStatus === 'left');
    });

    it('refuses the owner leaving while other members remain', function () {
        $group = groupWithMembers();

        $this->actingAs($this->owner)->post(groupUrl($group, 'leave'))->assertSessionHasErrors('group');

        expect(rowOf($group, $this->owner)->status)->toBe(GroupMembershipStatus::Member)
            ->and($group->fresh()?->owner_id)->toBe($this->owner->id);
    });

    it('soft-deletes the group when its last member leaves, withdraws its invitations and hides it from everybody', function () {
        $group = createGroupAs($this->owner);
        $invitation = inviteAs($this->owner, $group, $this->xena);

        $this->actingAs($this->owner)->post(groupUrl($group, 'leave'))->assertSessionHasNoErrors();

        expect(Group::query()->find($group->id))->toBeNull()
            ->and(Group::withTrashed()->find($group->id)?->deleted_at)->not->toBeNull()
            ->and($invitation->refresh()->status)->toBe(GroupMembershipStatus::Withdrawn)
            ->and(rowOf($group, $this->owner)->status)->toBe(GroupMembershipStatus::Left);

        foreach ([$this->owner, $this->xena, $this->teacher, $this->admin] as $user) {
            $this->actingAs($user)->get(groupsUrl())
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->has('groups', 0));
            $this->actingAs($user)->get(groupUrl($group))->assertNotFound();
        }
    });
});

describe('owner actions', function () {
    it('transfers ownership to another member', function () {
        $group = groupWithMembers();

        $this->actingAs($this->owner)->post(groupUrl($group, 'owner'), ['student_id' => $this->bob->id])->assertSessionHasNoErrors();

        expect($group->fresh()?->owner_id)->toBe($this->bob->id);

        // The former owner may now leave.
        $this->actingAs($this->owner)->post(groupUrl($group, 'leave'))->assertSessionHasNoErrors();
        expect(rowOf($group, $this->owner)->status)->toBe(GroupMembershipStatus::Left);
    });

    it('refuses a transfer to a student who is not a current member', function () {
        $group = createGroupAs($this->owner);
        inviteAs($this->owner, $group, $this->xena);

        foreach ([$this->xena, $this->yuri] as $student) {
            $this->actingAs($this->owner)->post(groupUrl($group, 'owner'), ['student_id' => $student->id])->assertSessionHasErrors('student_id');
        }

        expect($group->fresh()?->owner_id)->toBe($this->owner->id);
    });

    it('renames the group', function () {
        $group = createGroupAs($this->owner);

        $this->actingAs($this->owner)->put(groupUrl($group), ['name' => 'Renamed'])->assertSessionHasNoErrors();

        expect($group->fresh()?->name)->toBe('Renamed');
    });

    it('refuses transfer, rename and dissolve by a non-owner member', function () {
        $group = groupWithMembers();

        $this->actingAs($this->alice)->post(groupUrl($group, 'owner'), ['student_id' => $this->alice->id])->assertForbidden();
        $this->actingAs($this->alice)->put(groupUrl($group), ['name' => 'Mine'])->assertForbidden();
        $this->actingAs($this->alice)->delete(groupUrl($group))->assertForbidden();

        $fresh = $group->fresh();
        expect($fresh?->owner_id)->toBe($this->owner->id)
            ->and($fresh?->name)->toBe('Team')
            ->and($fresh?->deleted_at)->toBeNull();
    });

    it('dissolves the group: soft-deleted, pending invitations withdrawn, its rows and row kept', function () {
        $group = groupWithMembers();
        $invitation = inviteAs($this->owner, $group, $this->xena);
        $rowsBefore = $group->memberships()->count();

        $this->actingAs($this->owner)->delete(groupUrl($group))->assertSessionHasNoErrors();

        $trashed = Group::withTrashed()->find($group->id);
        expect(Group::query()->find($group->id))->toBeNull()
            ->and($trashed)->not->toBeNull()
            ->and($trashed?->deleted_at)->not->toBeNull()
            ->and($invitation->refresh()->status)->toBe(GroupMembershipStatus::Withdrawn)
            // Nothing that refers to the group is deleted: the row stays for
            // attempts, topic choices, proposals and bookings (SCOPE-025).
            ->and(DB::table('groups')->where('id', $group->id)->exists())->toBeTrue()
            ->and(GroupMembership::query()->where('group_id', $group->id)->count())->toBe($rowsBefore);

        $this->actingAs($this->alice)->get(groupUrl($group))->assertNotFound();
    });
});

describe('unenrolment and archiving', function () {
    it('passes ownership of an unenrolled owner to the member who joined first', function () {
        $group = groupWithMembers();
        $other = createGroupAs($this->bob, 'Other');
        $pending = inviteAs($this->bob, $other, $this->owner);
        Event::fake([GroupMembershipChanged::class]);

        $this->actingAs($this->teacher)
            ->delete($this->tenantUrl($this->acme, "courses/{$this->space->id}/students/{$this->enrolments[$this->owner->id]->id}"))
            ->assertSessionHasNoErrors();

        $row = rowOf($group, $this->owner);
        expect($row->status)->toBe(GroupMembershipStatus::Left)
            ->and($row->left_at)->not->toBeNull()
            ->and($group->fresh()?->owner_id)->toBe($this->alice->id)
            ->and($pending->refresh()->status)->toBe(GroupMembershipStatus::Withdrawn);
        Event::assertDispatchedTimes(GroupMembershipChanged::class, 2);
    });

    it('deletes the group when the unenrolled student was its only member', function () {
        $group = createGroupAs($this->owner);
        $invitation = inviteAs($this->owner, $group, $this->xena);

        $this->actingAs($this->teacher)
            ->delete($this->tenantUrl($this->acme, "courses/{$this->space->id}/students/{$this->enrolments[$this->owner->id]->id}"))
            ->assertSessionHasNoErrors();

        expect(Group::query()->find($group->id))->toBeNull()
            ->and($invitation->refresh()->status)->toBe(GroupMembershipStatus::Withdrawn);
    });

    it('ends every membership, withdraws every invitation and soft-deletes every group when the space is archived', function () {
        $first = groupWithMembers();
        $second = createGroupAs($this->xena, 'Second');
        inviteAs($this->owner, $first, $this->yuri);
        inviteAs($this->xena, $second, $this->alice);

        $otherSpace = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
        Enrolment::factory()->for($otherSpace, 'courseSpace')->for($this->owner, 'student')->create();
        $untouched = $this->asTenant($this->acme, fn () => app(CreateGroup::class)->handle($this->owner, $otherSpace, 'Elsewhere'));

        $this->actingAs($this->teacher)
            ->post($this->tenantUrl($this->acme, "courses/{$this->space->id}/archive"))
            ->assertSessionHasNoErrors();

        $rows = GroupMembership::query()->whereIn('group_id', [$first->id, $second->id])->get();
        expect($rows->where('status', GroupMembershipStatus::Member))->toHaveCount(0)
            ->and($rows->where('status', GroupMembershipStatus::Invited))->toHaveCount(0)
            ->and($rows->where('status', GroupMembershipStatus::Left)->count())->toBe(4)
            ->and($rows->where('status', GroupMembershipStatus::Withdrawn)->count())->toBe(2)
            ->and($rows->where('status', GroupMembershipStatus::Left)->every(fn (GroupMembership $row): bool => $row->left_at !== null))->toBeTrue()
            ->and(Group::query()->whereIn('id', [$first->id, $second->id])->count())->toBe(0)
            ->and(Group::withTrashed()->whereIn('id', [$first->id, $second->id])->count())->toBe(2)
            ->and($untouched->fresh())->not->toBeNull()
            ->and(rowOf($untouched, $this->owner)->status)->toBe(GroupMembershipStatus::Member);
    });
});

describe('teachers and org admins', function () {
    it('shows the owning teacher every group with members and pending invitations, read-only', function () {
        $group = groupWithMembers();
        inviteAs($this->owner, $group, $this->xena);
        createGroupAs($this->yuri, 'Solo');

        $this->actingAs($this->teacher)->get(groupsUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Courses/Groups/Index')
                ->where('canCreate', false)
                ->has('groups', 2)
                ->where('groups.1.name', 'Team')
                ->has('groups.1.members', 3)
                ->has('groups.1.invitations', 1)
                ->where('groups.1.invitations.0.student_id', $this->xena->id)
                ->where('groups.1.can', fn ($can) => collect($can)->every(fn ($allowed) => $allowed === false)));

        $this->actingAs($this->teacher)->get(groupUrl($group))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('group.members', 3)->where('invitableStudents', []));
    });

    it('refuses another teacher', function () {
        $group = createGroupAs($this->owner);

        $this->actingAs($this->otherTeacher)->get(groupsUrl())->assertForbidden();
        $this->actingAs($this->otherTeacher)->get(groupUrl($group))->assertForbidden();
    });

    it('shows an org admin the groups of any space of their organisation, read-only', function () {
        $group = groupWithMembers();

        $this->actingAs($this->admin)->get(groupsUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canCreate', false)
                ->has('groups', 1)
                ->has('groups.0.members', 3)
                ->where('groups.0.can', fn ($can) => collect($can)->every(fn ($allowed) => $allowed === false)));

        $this->actingAs($this->admin)->get(groupUrl($group))->assertOk();
    });

    it('lets neither the teacher nor an org admin change a group', function () {
        $group = groupWithMembers();
        $invitation = inviteAs($this->owner, $group, $this->xena);

        foreach ([$this->teacher, $this->admin] as $user) {
            $this->actingAs($user)->post(groupsUrl(), ['name' => 'Staff'])->assertForbidden();
            $this->actingAs($user)->put(groupUrl($group), ['name' => 'Staff'])->assertForbidden();
            $this->actingAs($user)->delete(groupUrl($group))->assertForbidden();
            $this->actingAs($user)->post(groupUrl($group, 'owner'), ['student_id' => $this->alice->id])->assertForbidden();
            $this->actingAs($user)->post(groupUrl($group, 'leave'))->assertForbidden();
            $this->actingAs($user)->post(groupUrl($group, 'invitations'), ['student_id' => $this->yuri->id])->assertForbidden();
            $this->actingAs($user)->delete(groupUrl($group, "invitations/{$invitation->id}"))->assertForbidden();
        }

        $fresh = $group->fresh();
        expect($fresh?->name)->toBe('Team')
            ->and($fresh?->owner_id)->toBe($this->owner->id)
            ->and($invitation->refresh()->status)->toBe(GroupMembershipStatus::Invited)
            ->and(Group::query()->count())->toBe(1)
            ->and($group->memberships()->count())->toBe(4);
    });
});

describe('students outside the group and other organisations', function () {
    it('refuses a student who is neither a member of nor invited to the group', function () {
        $group = createGroupAs($this->owner);
        inviteAs($this->owner, $group, $this->xena);

        $this->actingAs($this->yuri)->get(groupUrl($group))->assertForbidden();
        $this->actingAs($this->yuri)->get(groupsUrl())
            ->assertInertia(fn (Assert $page) => $page->has('groups', 0));

        // An invitee sees the group.
        $this->actingAs($this->xena)->get(groupUrl($group))->assertOk();
        $this->actingAs($this->xena)->get(groupsUrl())
            ->assertInertia(fn (Assert $page) => $page->has('groups', 1)->where('groups.0.can.respond', true));
    });

    it('does not find a group of another organisation', function () {
        $group = createGroupAs($this->owner);

        $beta = $this->createOrganization(['subdomain' => 'beta']);
        [$betaStudent, $betaSpace] = $this->asTenant($beta, function () {
            $student = User::factory()->student()->create();
            $space = CourseSpace::factory()->create();
            Enrolment::factory()->for($space, 'courseSpace')->for($student, 'student')->create();

            return [$student, $space];
        });

        // On its own subdomain, organisation B does not find A's space or group.
        $this->actingAs($betaStudent)
            ->get($this->tenantUrl($beta, "courses/{$this->space->id}/groups/{$group->id}"))
            ->assertNotFound();
        $this->actingAs($betaStudent)
            ->get($this->tenantUrl($beta, "courses/{$betaSpace->id}/groups/{$group->id}"))
            ->assertNotFound();
    });
});

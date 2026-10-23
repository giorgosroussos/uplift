<?php

use App\Modules\Courses\Actions\CreateGroup;
use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Exceptions\InvalidGroupMembershipTransition;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

/*
| OWN-005 on ENT-GroupMembership: a student sees memberships only of groups
| they belong to or are invited to; only members invite and withdraw, only
| the owner renames, transfers or dissolves; a teacher sees groups only of
| course spaces they own, read-only; an org admin sees groups of any course
| space of their organisation, read-only. And the ENT-GroupMembership state
| model.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->admin = User::factory()->orgAdmin()->create();
    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
    $this->otherSpace = CourseSpace::factory()->for($this->otherTeacher, 'teacher')->create();

    [$this->owner, $this->member, $this->invitee, $this->stranger] = User::factory()->student()->count(4)->create()->all();
    foreach ([$this->owner, $this->member, $this->invitee, $this->stranger] as $student) {
        Enrolment::factory()->for($this->space, 'courseSpace')->for($student, 'student')->create();
    }

    $this->group = app(CreateGroup::class)->handle($this->owner, $this->space, 'Team');
    $this->memberRow = GroupMembership::factory()->for($this->group, 'group')->for($this->member, 'student')->create();
    $this->memberRow->transitionTo(GroupMembershipStatus::Member);
    $this->invitation = GroupMembership::factory()->for($this->group, 'group')->for($this->invitee, 'student')->create();
    $this->ownerRow = GroupMembership::query()->where('student_id', $this->owner->id)->firstOrFail();

    $this->snapshot = fn (): array => [
        Group::withTrashed()->get()->map->getAttributes()->all(),
        GroupMembership::query()->orderBy('id')->get()->map->getAttributes()->all(),
    ];
});

it('[ROLE-Student] denies reads and changes the rule does not grant and changes nothing', function () {
    $before = ($this->snapshot)();
    $stranger = Gate::forUser($this->stranger);
    $member = Gate::forUser($this->member);
    $invitee = Gate::forUser($this->invitee);

    // A stranger sees nothing and changes nothing.
    expect($stranger->allows('view', $this->group))->toBeFalse()
        ->and($stranger->allows('view', $this->memberRow))->toBeFalse()
        ->and($stranger->allows('invite', $this->group))->toBeFalse()
        ->and($stranger->allows('withdraw', $this->invitation))->toBeFalse()
        ->and($stranger->allows('respond', $this->invitation))->toBeFalse()
        ->and($stranger->allows('leave', $this->group))->toBeFalse();

    // An invitee sees the group but neither invites nor withdraws.
    expect($invitee->allows('view', $this->group))->toBeTrue()
        ->and($invitee->allows('invite', $this->group))->toBeFalse()
        ->and($invitee->allows('withdraw', $this->invitation))->toBeFalse()
        ->and($invitee->allows('update', $this->group))->toBeFalse();

    // A member invites and withdraws but does not rename, transfer or dissolve,
    // and answers no one else's invitation.
    expect($member->allows('invite', $this->group))->toBeTrue()
        ->and($member->allows('withdraw', $this->invitation))->toBeTrue()
        ->and($member->allows('respond', $this->invitation))->toBeFalse()
        ->and($member->allows('update', $this->group))->toBeFalse()
        ->and($member->allows('transferOwnership', $this->group))->toBeFalse()
        ->and($member->allows('delete', $this->group))->toBeFalse();

    // Nobody edits or deletes a membership row directly.
    foreach ([$this->owner, $this->member, $this->invitee] as $student) {
        expect(Gate::forUser($student)->allows('update', $this->memberRow))->toBeFalse()
            ->and(Gate::forUser($student)->allows('delete', $this->memberRow))->toBeFalse();
    }

    $this->actingAs($this->stranger)->get($this->tenantUrl($this->acme, "courses/{$this->space->id}/groups/{$this->group->id}"))->assertForbidden();
    $this->actingAs($this->stranger)->post($this->tenantUrl($this->acme, "courses/{$this->space->id}/groups/{$this->group->id}/invitations"), ['student_id' => $this->stranger->id])->assertForbidden();
    $this->actingAs($this->stranger)->post($this->tenantUrl($this->acme, "courses/{$this->space->id}/groups/{$this->group->id}/invitations/{$this->invitation->id}/accept"))->assertForbidden();
    $this->actingAs($this->member)->put($this->tenantUrl($this->acme, "courses/{$this->space->id}/groups/{$this->group->id}"), ['name' => 'Mine'])->assertForbidden();
    $this->actingAs($this->member)->delete($this->tenantUrl($this->acme, "courses/{$this->space->id}/groups/{$this->group->id}"))->assertForbidden();

    expect(($this->snapshot)())->toBe($before);
});

it('[ROLE-Teacher] denies reads and changes the rule does not grant and changes nothing', function () {
    $before = ($this->snapshot)();
    $owning = Gate::forUser($this->teacher);
    $other = Gate::forUser($this->otherTeacher);

    expect($owning->allows('view', $this->group))->toBeTrue()
        ->and($owning->allows('view', $this->memberRow))->toBeTrue()
        ->and($other->allows('view', $this->group))->toBeFalse()
        ->and($other->allows('view', $this->memberRow))->toBeFalse()
        ->and($other->allows('viewGroups', [Group::class, $this->space]))->toBeFalse();

    foreach ([$owning, $other] as $gate) {
        expect($gate->allows('createGroup', [Group::class, $this->space]))->toBeFalse()
            ->and($gate->allows('invite', $this->group))->toBeFalse()
            ->and($gate->allows('withdraw', $this->invitation))->toBeFalse()
            ->and($gate->allows('respond', $this->invitation))->toBeFalse()
            ->and($gate->allows('update', $this->group))->toBeFalse()
            ->and($gate->allows('transferOwnership', $this->group))->toBeFalse()
            ->and($gate->allows('delete', $this->group))->toBeFalse()
            ->and($gate->allows('update', $this->memberRow))->toBeFalse()
            ->and($gate->allows('delete', $this->memberRow))->toBeFalse();
    }

    $this->actingAs($this->otherTeacher)->get($this->tenantUrl($this->acme, "courses/{$this->space->id}/groups/{$this->group->id}"))->assertForbidden();
    $this->actingAs($this->teacher)->delete($this->tenantUrl($this->acme, "courses/{$this->space->id}/groups/{$this->group->id}/invitations/{$this->invitation->id}"))->assertForbidden();

    expect(($this->snapshot)())->toBe($before);
});

it('[ROLE-OrgAdmin] denies reads and changes the rule does not grant and changes nothing', function () {
    $before = ($this->snapshot)();
    $gate = Gate::forUser($this->admin);

    expect($gate->allows('view', $this->group))->toBeTrue()
        ->and($gate->allows('viewGroups', [Group::class, $this->otherSpace]))->toBeTrue()
        ->and($gate->allows('createGroup', [Group::class, $this->space]))->toBeFalse()
        ->and($gate->allows('invite', $this->group))->toBeFalse()
        ->and($gate->allows('withdraw', $this->invitation))->toBeFalse()
        ->and($gate->allows('respond', $this->invitation))->toBeFalse()
        ->and($gate->allows('update', $this->group))->toBeFalse()
        ->and($gate->allows('transferOwnership', $this->group))->toBeFalse()
        ->and($gate->allows('delete', $this->group))->toBeFalse()
        ->and($gate->allows('update', $this->memberRow))->toBeFalse()
        ->and($gate->allows('delete', $this->memberRow))->toBeFalse();

    // An admin of another organisation sees nothing.
    $beta = $this->createOrganization(['subdomain' => 'beta']);
    $betaAdmin = $this->asTenant($beta, fn () => User::factory()->orgAdmin()->create());
    expect(Gate::forUser($betaAdmin)->allows('view', $this->group))->toBeFalse()
        ->and(Gate::forUser($betaAdmin)->allows('view', $this->memberRow))->toBeFalse();

    $this->actingAs($this->admin)->post($this->tenantUrl($this->acme, "courses/{$this->space->id}/groups/{$this->group->id}/leave"))->assertForbidden();

    expect(($this->snapshot)())->toBe($before);
});

describe('ENT-GroupMembership state model', function () {
    it('rejects every transition not in the state model and keeps the state', function (GroupMembershipStatus $from, GroupMembershipStatus $to) {
        $row = GroupMembership::factory()->for($this->group, 'group')->for($this->stranger, 'student')->create();
        $path = match ($from) {
            GroupMembershipStatus::Invited => [],
            GroupMembershipStatus::Member => [GroupMembershipStatus::Member],
            GroupMembershipStatus::Declined => [GroupMembershipStatus::Declined],
            GroupMembershipStatus::Withdrawn => [GroupMembershipStatus::Withdrawn],
            GroupMembershipStatus::Left => [GroupMembershipStatus::Member, GroupMembershipStatus::Left],
        };
        foreach ($path as $step) {
            $row->transitionTo($step);
        }

        expect(fn () => $row->transitionTo($to))->toThrow(InvalidGroupMembershipTransition::class)
            ->and($row->status)->toBe($from);

        // Also through a plain save (assigning the same status is no change).
        $row->status = $to;
        if ($from !== $to) {
            expect(fn () => $row->save())->toThrow(InvalidGroupMembershipTransition::class);
        }
        expect($row->status)->toBe($from)
            ->and($row->fresh()?->status)->toBe($from);
    })->with(function (): array {
        $cases = [];
        foreach (GroupMembershipStatus::cases() as $from) {
            foreach (GroupMembershipStatus::cases() as $to) {
                if (! $from->canTransitionTo($to)) {
                    $cases["{$from->value} -> {$to->value}"] = [$from, $to];
                }
            }
        }

        return $cases;
    });

    it('allows exactly the listed transitions', function () {
        $allowed = [];
        foreach (GroupMembershipStatus::cases() as $from) {
            foreach ($from->allowedTransitions() as $to) {
                $allowed[] = "{$from->value} -> {$to->value}";
            }
        }

        expect($allowed)->toBe(['invited -> member', 'invited -> declined', 'invited -> withdrawn', 'member -> left']);
    });

    it('creates rows only as invited, except the creator\'s own first membership', function () {
        foreach ([GroupMembershipStatus::Member, GroupMembershipStatus::Declined, GroupMembershipStatus::Withdrawn, GroupMembershipStatus::Left] as $status) {
            expect(fn () => GroupMembership::factory()->for($this->group, 'group')->for($this->stranger, 'student')->create(['status' => $status]))
                ->toThrow(InvalidGroupMembershipTransition::class);
        }

        expect(GroupMembership::query()->where('student_id', $this->stranger->id)->exists())->toBeFalse()
            ->and($this->ownerRow->status)->toBe(GroupMembershipStatus::Member)
            ->and($this->ownerRow->joined_at)->not->toBeNull();
    });
});

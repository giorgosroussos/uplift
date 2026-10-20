<?php

use App\Modules\Identity\Database\Factories\InvitationFactory;
use App\Modules\Identity\Enums\InvitationStatus;
use App\Modules\Identity\Exceptions\InvalidInvitationTransition;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Tenancy\Support\OrganizationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/*
| ENT-Invitation: state model, token storage, tenant isolation, retention.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
});

function makeInvitation(mixed $organization): Invitation
{
    return Invitation::factory()->for($organization, 'organization')->create();
}

function rawInvitationRow(Invitation $invitation): object
{
    return DB::table('invitations')->where('id', $invitation->getKey())->first();
}

it('isolates invitations per organisation', function () {
    $this->assertTenantIsolated(Invitation::class, fn () => Invitation::factory()->create());
});

it('is created pending and cannot be created in another state', function (InvitationStatus $status) {
    $invitation = Invitation::factory()->for($this->acme, 'organization')->make();
    $invitation->status = $status;

    expect(fn () => $invitation->save())->toThrow(InvalidInvitationTransition::class);
    expect(DB::table('invitations')->count())->toBe(0);
})->with([InvitationStatus::Accepted, InvitationStatus::Revoked, InvitationStatus::Expired]);

it('allows exactly the transitions of the state model', function (InvitationStatus $to) {
    $invitation = makeInvitation($this->acme);

    $invitation->transitionTo($to);

    expect(rawInvitationRow($invitation)->status)->toBe($to->value);
})->with([InvitationStatus::Accepted, InvitationStatus::Revoked, InvitationStatus::Expired]);

it('rejects every transition not in the state model and leaves the state unchanged', function (InvitationStatus $from, InvitationStatus $to) {
    $invitation = Invitation::factory()->for($this->acme, 'organization')->inStatus($from)->create();

    expect(fn () => $invitation->transitionTo($to))->toThrow(InvalidInvitationTransition::class);
    expect(rawInvitationRow($invitation)->status)->toBe($from->value);

    // Also through a plain save, bypassing transitionTo() (saving the same
    // status writes nothing).
    $invitation->status = $to;
    if ($from !== $to) {
        expect(fn () => $invitation->save())->toThrow(InvalidInvitationTransition::class);
    }
    expect($invitation->status)->toBe($from)
        ->and(rawInvitationRow($invitation)->status)->toBe($from->value);
})->with(function () {
    foreach ([InvitationStatus::Accepted, InvitationStatus::Revoked, InvitationStatus::Expired] as $from) {
        foreach (InvitationStatus::cases() as $to) {
            yield "{$from->value} -> {$to->value}" => [$from, $to];
        }
    }
});

it('rejects a transition from pending to pending', function () {
    $invitation = makeInvitation($this->acme);

    expect(fn () => $invitation->transitionTo(InvitationStatus::Pending))->toThrow(InvalidInvitationTransition::class);
    expect(rawInvitationRow($invitation)->status)->toBe('pending');
});

it('names no email in the transition error', function () {
    $invitation = Invitation::factory()->for($this->acme, 'organization')->inStatus(InvitationStatus::Accepted)->create(['email' => 'secret@example.com']);

    try {
        $invitation->transitionTo(InvitationStatus::Revoked);
    } catch (InvalidInvitationTransition $e) {
        expect($e->getMessage())->not->toContain('secret@example.com');

        return;
    }

    $this->fail('No exception thrown.');
});

it('stores neither the token nor a plain hash: token_hash is encrypted at rest', function () {
    $invitation = makeInvitation($this->acme);
    $token = (string) InvitationFactory::$lastToken;
    $stored = rawInvitationRow($invitation)->token_hash;

    expect($stored)->not->toContain($token)
        ->and(strlen($stored))->toBeLessThanOrEqual(255)
        ->and(Crypt::decryptString($stored))->not->toBe($token)
        ->and($invitation->fresh()->matchesToken($token))->toBeTrue()
        ->and($invitation->fresh()->matchesToken($token.'x'))->toBeFalse()
        ->and($invitation->toArray())->not->toHaveKey('token_hash');
});

it('stores emails in lower case', function () {
    $invitation = Invitation::factory()->for($this->acme, 'organization')->create(['email' => ' Mixed@Example.COM ']);

    expect(rawInvitationRow($invitation)->email)->toBe('mixed@example.com');
});

it('deletes invitations 30 days after they stopped being pending', function () {
    $this->travelTo(now()->subDays(31), function () {
        $this->old = Invitation::factory()->for($this->acme, 'organization')->inStatus(InvitationStatus::Accepted)->create();
        $this->oldPendingButExpired = Invitation::factory()->for($this->acme, 'organization')->create(['expires_at' => now()]);
        $this->oldStillPending = Invitation::factory()->for($this->acme, 'organization')->create(['expires_at' => now()->addDays(60)]);
    });
    $recent = Invitation::factory()->for($this->acme, 'organization')->inStatus(InvitationStatus::Revoked)->create();
    $pending = makeInvitation($this->acme);

    Artisan::call('model:prune', ['--model' => [Invitation::class]]);

    expect(Invitation::query()->withoutGlobalScope(OrganizationScope::class)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$recent->getKey(), $pending->getKey(), $this->oldStillPending->getKey()])->sort()->values()->all());
});

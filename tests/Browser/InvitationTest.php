<?php

use App\Modules\Identity\Database\Factories\InvitationFactory;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Support\OrganizationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Browser flows of UC-032 (an org admin invites a user) and UC-005 (the
| invitee accepts and lands on their dashboard).
|
| The browser test server always answers as http://127.0.0.1:<port>, so the
| organisation "127" on the apex domain "0.0.1" stands in for a subdomain.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => '0.0.1', 'app.locale' => 'en']);

    $this->organization = $this->createOrganization(['subdomain' => '127', 'name' => 'Acme School']);
});

it('lets an org admin invite a user from the user list', function () {
    $admin = User::factory()->for($this->organization, 'organization')->orgAdmin()->create();
    $this->actingAs($admin);

    visit('/users')
        ->assertSee('Users')
        ->fill('email', 'new.teacher@acme.test')
        ->select('role', 'teacher')
        ->click('@invite-button')
        ->assertSee('new.teacher@acme.test')
        ->assertNoJavaScriptErrors();

    expect(Invitation::query()->withoutGlobalScope(OrganizationScope::class)->where('email', 'new.teacher@acme.test')->sole()->role)
        ->toBe(Role::Teacher);
});

it('lets an invitee accept an invitation and reach the teacher dashboard', function () {
    $invitation = Invitation::factory()->for($this->organization, 'organization')->role(Role::Teacher)->create();
    $token = InvitationFactory::$lastToken;

    visit("/invitation/{$invitation->getKey()}/{$token}")
        ->assertSee('Acme School')
        ->fill('name', 'Ada Lovelace')
        ->fill('password', 'a-long-enough-password')
        ->fill('password_confirmation', 'a-long-enough-password')
        ->click('@accept-invitation-button')
        ->assertPathIs('/dashboard')
        ->assertSee('Teacher dashboard')
        ->assertNoJavaScriptErrors();
});

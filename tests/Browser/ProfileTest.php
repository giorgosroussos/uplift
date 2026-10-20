<?php

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Enums\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Browser flow of UC-006: a user edits nickname, avatar and language, and the
| UI switches language at once; MFA settings are offered only to org admins.
|
| The browser test server always answers as http://127.0.0.1:<port>, so the
| organisation "127" on the apex domain "0.0.1" stands in for a subdomain.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => '0.0.1', 'app.locale' => 'en']);

    $this->organization = $this->createOrganization(['subdomain' => '127', 'default_locale' => Locale::English]);
});

it('lets a student pick a nickname, a preset avatar and Greek', function () {
    $student = User::factory()->for($this->organization, 'organization')->student()->create(['name' => 'Sam Student']);
    $this->actingAs($student);

    visit('/settings/profile')
        ->assertSee('Profile')
        ->assertDontSee('Two-factor authentication')
        ->fill('nickname', 'Fox_7')
        ->click('@avatar-avatar-03')
        ->select('locale', 'el')
        ->click('@profile-save')
        ->assertSee('Το προφίλ σας αποθηκεύτηκε.')
        ->assertSee('Ψευδώνυμο')
        ->assertNoJavaScriptErrors();

    $student = $student->fresh();
    expect($student->nickname)->toBe('Fox_7')
        ->and($student->avatar)->toBe('avatar-03')
        ->and($student->locale)->toBe(Locale::Greek);
});

it('shows why a nickname is rejected', function () {
    User::factory()->for($this->organization, 'organization')->teacher()->create(['nickname' => 'Fox_7']);
    $student = User::factory()->for($this->organization, 'organization')->student()->create();
    $this->actingAs($student);

    visit('/settings/profile')
        ->fill('nickname', 'fox_7')
        ->click('@profile-save')
        ->assertSee('This nickname is already used in your organisation.')
        ->fill('nickname', 'fox 7')
        ->click('@profile-save')
        ->assertSee('The nickname may contain only letters, digits and the characters _ - .');
});

it('offers two-factor authentication to an org admin', function () {
    $admin = User::factory()->for($this->organization, 'organization')->orgAdmin()->create();
    $this->actingAs($admin);

    visit('/settings/profile')
        ->assertSee('Two-factor authentication')
        ->click('Two-factor authentication')
        ->assertSee('Confirm password')
        ->assertNoJavaScriptErrors();
});

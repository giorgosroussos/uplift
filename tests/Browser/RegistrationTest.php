<?php

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Browser flow of UC-001: sign up on the apex domain with the live
| subdomain check.
|
| The browser test server always answers as http://127.0.0.1:<port>, so
| "127.0.0.1" stands in for the apex domain. The redirect to the new
| organisation's subdomain is covered by the feature tests.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => '127.0.0.1', 'app.locale' => 'en']);

    $this->createOrganization(['subdomain' => 'taken-org']);
});

it('checks the subdomain live and shows why it cannot be used', function () {
    visit('/register')
        ->assertSee('Register your organisation')
        ->fill('subdomain', 'taken-org')
        ->assertSee('This subdomain is already taken.')
        ->fill('subdomain', 'www')
        ->assertSee('This subdomain is reserved by the platform.')
        ->fill('subdomain', 'acme')
        ->assertSee('This subdomain is available.')
        ->assertNoJavaScriptErrors();
});

it('registers an organisation and its admin', function () {
    visit('/register')
        ->fill('organization_name', 'Acme')
        ->fill('subdomain', 'acme')
        ->assertSee('This subdomain is available.')
        ->fill('name', 'Ada Admin')
        ->fill('email', 'ada@acme.test')
        ->fill('password', 'correct-horse-battery')
        ->fill('password_confirmation', 'correct-horse-battery')
        ->click('@register-button')
        ->wait(1);

    $organization = Organization::query()->where('subdomain', 'acme')->sole();

    expect(User::withoutGlobalScopes()->where('organization_id', $organization->id)->count())->toBe(1);
});

<?php

use App\Modules\Identity\Database\Factories\UserFactory;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Browser flow of UC-003: log in on the organisation subdomain and reach the
| role's dashboard.
|
| The browser test server always answers as http://127.0.0.1:<port>, so the
| organisation "127" on the apex domain "0.0.1" stands in for a subdomain.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => '0.0.1', 'app.locale' => 'en']);

    $this->organization = $this->createOrganization(['subdomain' => '127', 'name' => 'Acme School']);
    User::factory()->for($this->organization, 'organization')->teacher()->create(['email' => 'teacher@acme.test']);
});

it('logs a teacher in and shows the teacher dashboard', function () {
    visit('/login')
        ->assertSee('Acme School')
        ->fill('email', 'teacher@acme.test')
        ->fill('password', UserFactory::PASSWORD)
        ->click('@login-button')
        ->assertPathIs('/dashboard')
        ->assertSee('Teacher dashboard')
        ->assertNoJavaScriptErrors();
});

it('shows a generic error for wrong credentials', function () {
    visit('/login')
        ->fill('email', 'teacher@acme.test')
        ->fill('password', 'wrong-password')
        ->click('@login-button')
        ->assertSee('These credentials do not match our records.')
        ->assertPathIs('/login');
});

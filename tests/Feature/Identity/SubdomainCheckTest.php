<?php

use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;

/*
| UC-001 step 2 — live subdomain availability check (RFC 9457 errors,
| 60 requests per minute per IP).
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);
    App::setLocale('en');

    $this->createOrganization(['subdomain' => 'acme']);
});

function subdomainCheckPath(string $subdomain): string
{
    return 'register/subdomain?subdomain='.urlencode($subdomain);
}

it('answers 200 for an available subdomain', function () {
    $this->getJson($this->apexUrl(subdomainCheckPath('Beta-1')))
        ->assertOk()
        ->assertExactJson(['subdomain' => 'beta-1', 'available' => true]);
});

it('answers RFC 9457 problem details naming why a subdomain is unavailable', function (string $subdomain, string $reason, string $detail) {
    $response = $this->getJson($this->apexUrl(subdomainCheckPath($subdomain)))
        ->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJson([
            'type' => 'urn:uplift:problem:subdomain-unavailable',
            'title' => 'Subdomain unavailable',
            'status' => 422,
            'detail' => $detail,
            'available' => false,
            'reason' => $reason,
            'errors' => ['subdomain' => [$detail]],
        ]);

    expect($response->json())->toHaveKeys(['type', 'title', 'status', 'detail']);
})->with([
    'taken' => ['acme', 'taken', 'This subdomain is already taken.'],
    'taken, other case' => ['ACME', 'taken', 'This subdomain is already taken.'],
    'reserved' => ['www', 'reserved', 'This subdomain is reserved by the platform.'],
    'invalid' => ['acme_1', 'invalid', 'Use 3 to 30 characters: lowercase letters, digits and hyphens, not at the start or end.'],
    'empty' => ['', 'invalid', 'Use 3 to 30 characters: lowercase letters, digits and hyphens, not at the start or end.'],
]);

it('gives the reason in Greek for the Greek UI', function () {
    App::setLocale('el');

    $this->getJson($this->apexUrl(subdomainCheckPath('acme')))
        ->assertStatus(422)
        ->assertJsonPath('detail', 'Αυτό το subdomain χρησιμοποιείται ήδη.');
});

it('limits the check to 60 requests per minute per IP with a problem details 429', function () {
    foreach (range(1, 60) as $request) {
        $this->getJson($this->apexUrl(subdomainCheckPath('beta')))->assertOk();
    }

    $this->getJson($this->apexUrl(subdomainCheckPath('beta')))
        ->assertStatus(429)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertHeader('Retry-After')
        ->assertJson(['status' => 429, 'title' => 'Too many requests']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->getJson($this->apexUrl(subdomainCheckPath('beta')))
        ->assertOk();
});

it('has no subdomain check on an organisation subdomain', function () {
    $acme = Organization::query()->sole();

    $this->getJson($this->tenantUrl($acme, subdomainCheckPath('beta')))->assertNotFound();
});

<?php

use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\Tenancy\Fixtures\TenantOwnedThing;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    Route::middleware('web')->get('/__tenant', fn () => app(TenantContext::class)->current()->subdomain ?? 'none');
    Route::middleware(['web', 'tenant'])->get('/__tenant-only', fn () => 'ok');
    Route::middleware(['web', 'tenant'])->get('/__things/{thing}', fn (TenantOwnedThing $thing) => $thing->name);

    TenantOwnedThing::createTable();
    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
});

it('resolves the organisation from its subdomain', function () {
    $this->get('http://acme.uplift.test/__tenant')->assertOk()->assertSeeText('acme');
    $this->get($this->tenantUrl($this->acme, '__tenant'))->assertOk()->assertSeeText('acme');
});

it('resolves subdomains case-insensitively', function () {
    $this->get('http://ACME.uplift.test/__tenant')->assertOk()->assertSeeText('acme');
});

it('has no tenant on the apex domain', function () {
    $this->get('http://uplift.test/__tenant')->assertOk()->assertSeeText('none');
});

it('has no tenant on a reserved subdomain', function (string $subdomain) {
    $this->get("http://{$subdomain}.uplift.test/__tenant")->assertOk()->assertSeeText('none');
})->with(['www', 'app', 'api', 'admin', 'mail']);

it('has no tenant on a host outside the apex domain', function () {
    $this->get('http://acme.example.com/__tenant')->assertOk()->assertSeeText('none');
});

it('returns 404 for an unknown subdomain', function (string $host) {
    $this->get("http://{$host}/__tenant")->assertNotFound();
})->with([
    'unknown organisation' => 'beta.uplift.test',
    'nested subdomain' => 'x.acme.uplift.test',
    'malformed subdomain' => 'ac_me.uplift.test',
]);

it('resolves a suspended organisation (access rules for suspension belong to later phases)', function () {
    Organization::factory()->suspended()->create(['subdomain' => 'frozen']);

    $this->get('http://frozen.uplift.test/__tenant')->assertOk()->assertSeeText('frozen');
});

it('does not carry a tenant over from one request to the next', function () {
    $this->get('http://acme.uplift.test/__tenant')->assertSeeText('acme');
    $this->get('http://uplift.test/__tenant')->assertSeeText('none');
});

it('limits tenant-only routes to organisation subdomains', function () {
    $this->get('http://acme.uplift.test/__tenant-only')->assertOk();
    $this->get('http://uplift.test/__tenant-only')->assertNotFound();
    $this->get('http://www.uplift.test/__tenant-only')->assertNotFound();
});

it('resolves the tenant before route model binding, so another tenant\'s records are not found', function () {
    $beta = $this->createOrganization(['subdomain' => 'beta']);
    $own = $this->asTenant($this->acme, fn () => TenantOwnedThing::query()->create(['name' => 'acme thing']));
    $foreign = $this->asTenant($beta, fn () => TenantOwnedThing::query()->create(['name' => 'beta thing']));

    $this->get("http://acme.uplift.test/__things/{$own->id}")->assertOk()->assertSeeText('acme thing');
    $this->get("http://acme.uplift.test/__things/{$foreign->id}")->assertNotFound();
    $this->get("http://beta.uplift.test/__things/{$foreign->id}")->assertOk()->assertSeeText('beta thing');
});

it('runs ResolveTenant first in the web middleware group', function () {
    $web = app('router')->getMiddlewareGroups()['web'];

    expect($web[0])->toBe(ResolveTenant::class);
});

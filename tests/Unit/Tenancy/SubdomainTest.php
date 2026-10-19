<?php

use App\Modules\Tenancy\Support\Subdomain;

it('reserves www, app, api, admin and mail', function (string $subdomain) {
    expect(Subdomain::isReserved($subdomain))->toBeTrue()
        ->and(Subdomain::isReserved(strtoupper($subdomain)))->toBeTrue()
        ->and(Subdomain::isAllowed($subdomain))->toBeFalse();
})->with(['www', 'app', 'api', 'admin', 'mail']);

it('does not reserve ordinary subdomains', function () {
    expect(Subdomain::isReserved('acme'))->toBeFalse()
        ->and(Subdomain::isReserved('www2'))->toBeFalse();
});

it('rejects malformed subdomains', function (string $subdomain) {
    expect(Subdomain::isWellFormed($subdomain))->toBeFalse()
        ->and(Subdomain::isAllowed($subdomain))->toBeFalse();
})->with([
    'two characters' => 'ab',
    'leading hyphen' => '-acme',
    'trailing hyphen' => 'acme-',
    'underscore' => 'acme_1',
    'dot' => 'ac.me',
    'non-latin' => 'σχολείο',
    '31 characters' => str_repeat('a', 31),
    'empty' => '',
]);

it('accepts 3-30 characters of a-z, 0-9 and inner hyphens', function (string $subdomain) {
    expect(Subdomain::isWellFormed($subdomain))->toBeTrue()
        ->and(Subdomain::isAllowed($subdomain))->toBeTrue();
})->with(['acme-1', 'abc', '123', str_repeat('a', 30), 'my-school-2']);

it('treats subdomains case-insensitively', function () {
    expect(Subdomain::normalize(' ACME '))->toBe('acme')
        ->and(Subdomain::isWellFormed('ACME'))->toBeTrue();
});

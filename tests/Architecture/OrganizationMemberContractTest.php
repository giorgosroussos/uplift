<?php

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Contracts\OrganizationMember;
use Illuminate\Support\Facades\File;

/*
| ARCH-008: Tenancy authorises organisation actions only through its own
| OrganizationMember contract. It never names Identity classes (deptrac) nor
| Identity's role values, and Identity's User implements the contract.
*/

it('keeps Identity role names and classes out of Tenancy', function () {
    foreach (File::allFiles(app_path('Modules/Tenancy')) as $file) {
        $source = $file->getContents();

        expect($source)
            ->not->toContain('App\\Modules\\Identity', "{$file->getRelativePathname()} references Identity")
            ->not->toMatch('/[\'"](org_admin|teacher|student)[\'"]/', "{$file->getRelativePathname()} names a role");
    }
});

it('has Identity\'s User implement OrganizationMember', function () {
    expect(is_subclass_of(User::class, OrganizationMember::class))->toBeTrue();
});

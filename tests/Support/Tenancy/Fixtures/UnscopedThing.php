<?php

namespace Tests\Support\Tenancy\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A model of a tenant-owned table that forgot BelongsToOrganization: the
 * isolation check must report it.
 */
class UnscopedThing extends Model
{
    use HasUuids;

    protected $table = 'tenancy_fixture_things';

    protected $guarded = [];
}

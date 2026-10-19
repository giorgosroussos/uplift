<?php

namespace Tests\Support\Tenancy\Fixtures;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tenant-owned model used to test the tenancy core itself.
 */
class TenantOwnedThing extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    protected $table = 'tenancy_fixture_things';

    protected $guarded = [];

    public static function createTable(): void
    {
        Schema::create('tenancy_fixture_things', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }
}

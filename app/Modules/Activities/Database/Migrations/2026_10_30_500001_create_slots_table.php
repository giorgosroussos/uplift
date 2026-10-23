<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ENT-Slot: a bookable time slot of a slot activity with the number of
| bookings it takes (SCOPE-011, SCOPE-028); removed with the slot activity.
| Times are stored in UTC (DATA-007).
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('slot_activity_id');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('location', 255)->nullable();
            $table->integer('capacity');
            $table->timestampsTz();

            $table->foreign(['organization_id', 'slot_activity_id'])
                ->references(['organization_id', 'activity_id'])->on('slot_activities')
                ->cascadeOnDelete();

            // Lets bookings reference a slot together with its organisation.
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'slot_activity_id']);
        });

        DB::statement('ALTER TABLE slots ADD CONSTRAINT slots_capacity_check CHECK (capacity >= 1)');
        DB::statement('ALTER TABLE slots ADD CONSTRAINT slots_times_check CHECK (ends_at > starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('slots');
    }
};

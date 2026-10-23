<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ENT-SlotActivity: the subtype of ENT-Activity for slot booking (Class Table
| Inheritance, DATA-001). The activity id is the primary key; organization_id
| is kept on the subtype too (DATA-007) and references the activity with it.
| slot_duration (seconds, 5 minutes to 24 hours) only pre-fills the end of
| new slots in the wizard and is never enforced (SCOPE-028).
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_activities', function (Blueprint $table) {
            $table->uuid('activity_id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->integer('slot_duration')->nullable();
            $table->timestampsTz();

            $table->foreign(['organization_id', 'activity_id'])
                ->references(['organization_id', 'id'])->on('activities')
                ->cascadeOnDelete();

            // Lets slots reference a slot activity together with its organisation.
            $table->unique(['organization_id', 'activity_id']);
        });

        DB::statement('ALTER TABLE slot_activities ADD CONSTRAINT slot_activities_slot_duration_check CHECK (slot_duration IS NULL OR slot_duration BETWEEN 300 AND 86400)');
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_activities');
    }
};

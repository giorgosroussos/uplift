<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ENT-Poll: the poll subtype of ENT-Activity (Class Table Inheritance,
| DATA-001). The activity id is the primary key; organization_id is kept on
| the subtype too (DATA-007) and references the activity with it.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table) {
            $table->uuid('activity_id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->integer('max_choices');
            $table->string('results_visibility');
            $table->timestampsTz();

            $table->foreign(['organization_id', 'activity_id'])
                ->references(['organization_id', 'id'])->on('activities')
                ->cascadeOnDelete();

            // Lets child tables reference a poll together with its organisation.
            $table->unique(['organization_id', 'activity_id']);
        });

        DB::statement('ALTER TABLE polls ADD CONSTRAINT polls_max_choices_check CHECK (max_choices >= 1)');
        DB::statement("ALTER TABLE polls ADD CONSTRAINT polls_results_visibility_check CHECK (results_visibility IN ('after_vote', 'after_close'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('polls');
    }
};

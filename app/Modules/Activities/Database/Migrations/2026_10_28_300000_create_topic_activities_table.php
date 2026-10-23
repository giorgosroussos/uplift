<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| ENT-TopicActivity: the subtype of ENT-Activity for topic selection (and,
| later, topic proposal) (Class Table Inheritance, DATA-001). The activity id
| is the primary key; organization_id is kept on the subtype too (DATA-007)
| and references the activity with it.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_activities', function (Blueprint $table) {
            $table->uuid('activity_id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['organization_id', 'activity_id'])
                ->references(['organization_id', 'id'])->on('activities')
                ->cascadeOnDelete();

            // Lets child tables reference a topic activity together with its organisation.
            $table->unique(['organization_id', 'activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_activities');
    }
};

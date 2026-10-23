<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ENT-Topic: a topic of a topic activity with the capacity the teacher sets
| (SCOPE-011); removed with the topic activity.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('topic_activity_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('capacity');
            $table->timestampsTz();

            $table->foreign(['organization_id', 'topic_activity_id'])
                ->references(['organization_id', 'activity_id'])->on('topic_activities')
                ->cascadeOnDelete();

            // Lets choices reference a topic together with its organisation.
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'topic_activity_id']);
        });

        DB::statement('ALTER TABLE topics ADD CONSTRAINT topics_capacity_check CHECK (capacity >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};

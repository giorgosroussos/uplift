<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ENT-Activity: base table of the five activity types (Class Table
| Inheritance, DATA-001). Subtype tables use the activity id as primary key
| and reference (organization_id, id) so they never point into another
| tenant (DATA-007).
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('course_space_id');
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestampTz('deadline_at')->nullable();
            $table->boolean('is_group')->default(false);
            $table->integer('group_size')->nullable();
            $table->string('status')->default('draft');
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            // DATA-007: activities are soft-deleted.
            $table->softDeletesTz();

            // Same organisation as the course space; removed with it.
            $table->foreign(['organization_id', 'course_space_id'])
                ->references(['organization_id', 'id'])->on('course_spaces')
                ->cascadeOnDelete();

            // Lets subtype tables reference an activity with its organisation.
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'course_space_id', 'status']);
        });

        DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_type_check CHECK (type IN ('quiz', 'poll', 'topic_selection', 'topic_proposal', 'slot_booking'))");
        DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_status_check CHECK (status IN ('draft', 'published', 'closed'))");
        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_group_size_check CHECK ((is_group AND group_size >= 2) OR (NOT is_group AND group_size IS NULL))');
        DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_published_at_check CHECK (status = 'draft' OR published_at IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('group_id');
            // Follows the student's erasure (SEC-003).
            $table->foreignUuid('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('status');
            $table->foreignUuid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('joined_at')->nullable();
            $table->timestampTz('left_at')->nullable();
            $table->timestampsTz();

            // Same organisation as the group; removed with it.
            $table->foreign(['organization_id', 'group_id'])
                ->references(['organization_id', 'id'])->on('groups')
                ->cascadeOnDelete();

            $table->index(['organization_id', 'group_id', 'status']);
            $table->index(['organization_id', 'student_id', 'status']);
        });

        DB::statement("ALTER TABLE group_memberships ADD CONSTRAINT group_memberships_status_check CHECK (status IN ('invited', 'member', 'declined', 'withdrawn', 'left'))");

        // SCOPE-025: at most one invited or member row per student and group;
        // a re-invitation after declined, withdrawn or left is a new row.
        DB::statement("CREATE UNIQUE INDEX group_memberships_open_unique ON group_memberships (organization_id, group_id, student_id) WHERE status IN ('invited', 'member')");
    }

    public function down(): void
    {
        Schema::dropIfExists('group_memberships');
    }
};

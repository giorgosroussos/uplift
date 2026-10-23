<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ENT-TopicProposal: a topic proposed by a student (individual activity) or
| a group (group activity) in a topic proposal activity (UC-015), decided by
| the owning teacher (UC-027). Its subtype row is ENT-TopicActivity. A
| holder has at most one pending or approved proposal per activity (partial
| unique indexes, also under concurrent requests); rejected proposals stay
| as history. Follows the student's erasure (SEC-003); a group is only ever
| soft-deleted, so its proposals stay (SCOPE-025). The description is
| personal data (SEC-003).
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('topic_activity_id');
            $table->foreignUuid('student_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->uuid('group_id')->nullable();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('status', 16)->default('pending');
            $table->text('teacher_comment')->nullable();
            $table->timestampsTz();

            $table->foreign(['organization_id', 'topic_activity_id'])
                ->references(['organization_id', 'activity_id'])->on('topic_activities')
                ->cascadeOnDelete();

            $table->foreign(['organization_id', 'group_id'])
                ->references(['organization_id', 'id'])->on('groups')
                ->cascadeOnDelete();

            $table->index(['organization_id', 'topic_activity_id', 'status']);
            $table->index(['organization_id', 'student_id']);
            $table->index(['organization_id', 'group_id']);
        });

        // Made either by one student or by one group.
        DB::statement('ALTER TABLE topic_proposals ADD CONSTRAINT topic_proposals_holder_check CHECK ((student_id IS NULL) <> (group_id IS NULL))');
        DB::statement("ALTER TABLE topic_proposals ADD CONSTRAINT topic_proposals_status_check CHECK (status IN ('pending', 'approved', 'rejected'))");

        // UC-015: no second pending proposal, and none after an approval.
        DB::statement("CREATE UNIQUE INDEX topic_proposals_open_student_unique ON topic_proposals (organization_id, topic_activity_id, student_id) WHERE student_id IS NOT NULL AND status IN ('pending', 'approved')");
        DB::statement("CREATE UNIQUE INDEX topic_proposals_open_group_unique ON topic_proposals (organization_id, topic_activity_id, group_id) WHERE group_id IS NOT NULL AND status IN ('pending', 'approved')");
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_proposals');
    }
};

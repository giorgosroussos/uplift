<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_spaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            // The owning teacher (SCOPE-007); a teacher with course spaces is not deleted.
            $table->foreignUuid('teacher_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('enrolment_policy');
            $table->string('enrolment_key')->nullable();
            $table->string('status')->default('active');
            $table->timestampsTz();

            // Lets child tables reference a course space together with its
            // organisation, so a child row can never point into another tenant.
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'teacher_id']);
        });

        DB::statement("ALTER TABLE course_spaces ADD CONSTRAINT course_spaces_enrolment_policy_check CHECK (enrolment_policy IN ('open', 'key'))");
        DB::statement("ALTER TABLE course_spaces ADD CONSTRAINT course_spaces_status_check CHECK (status IN ('active', 'archived'))");
        DB::statement("ALTER TABLE course_spaces ADD CONSTRAINT course_spaces_enrolment_key_check CHECK (enrolment_policy <> 'key' OR (enrolment_key IS NOT NULL AND enrolment_key <> ''))");
    }

    public function down(): void
    {
        Schema::dropIfExists('course_spaces');
    }
};

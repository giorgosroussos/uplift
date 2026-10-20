<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrolments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('course_space_id');
            // Follows the student's erasure (SEC-003).
            $table->foreignUuid('student_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('enrolled_at');
            $table->timestampsTz();

            // Same organisation as the course space; removed with it.
            $table->foreign(['organization_id', 'course_space_id'])
                ->references(['organization_id', 'id'])->on('course_spaces')
                ->cascadeOnDelete();

            // One enrolment per student and course space (ENT-Enrolment).
            $table->unique(['organization_id', 'course_space_id', 'student_id']);
            $table->index(['organization_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrolments');
    }
};

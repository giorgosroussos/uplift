<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ENT-QuizAttempt: one attempt of a self-paced quiz. Points are exact
| decimals (DATA-003); timestamps are UTC server times (DATA-004).
| group_id gets its foreign key together with groups (PHASE-4).
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('quiz_id');
            $table->foreignUuid('student_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->uuid('group_id')->nullable();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->decimal('earned_points', 8, 2)->nullable();
            $table->decimal('max_points', 8, 2)->nullable();
            $table->string('status')->default('in_progress');
            $table->timestampsTz();

            // ENT-Quiz -> ENT-QuizAttempt: on delete restrict.
            $table->foreign(['organization_id', 'quiz_id'])
                ->references(['organization_id', 'activity_id'])->on('quizzes')
                ->restrictOnDelete();

            // Lets answers reference an attempt with its organisation.
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'quiz_id', 'status']);
            $table->index(['organization_id', 'student_id']);
        });

        DB::statement("ALTER TABLE quiz_attempts ADD CONSTRAINT quiz_attempts_status_check CHECK (status IN ('in_progress', 'submitted', 'graded'))");
        DB::statement('ALTER TABLE quiz_attempts ADD CONSTRAINT quiz_attempts_participant_check CHECK (student_id IS NOT NULL OR group_id IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};

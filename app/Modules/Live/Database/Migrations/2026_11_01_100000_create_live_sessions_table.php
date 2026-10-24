<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ENT-LiveSession, ENT-LiveParticipant and ENT-LiveAnswer (PHASE-5): the
| real-time run of a live quiz with one common end time (SCOPE-021). All
| live state lives in PostgreSQL, so a restart of Reverb or the queue
| worker loses nothing (OPS-001). Timestamps are UTC server times; points
| are exact decimals (DATA-003). Live answers are never soft-deleted
| (DATA-007).
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('quiz_id');
            $table->foreignUuid('host_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('lobby');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            // ENT-Quiz -> ENT-LiveSession: on delete restrict.
            $table->foreign(['organization_id', 'quiz_id'])
                ->references(['organization_id', 'activity_id'])->on('quizzes')
                ->restrictOnDelete();

            // Lets participants reference a session with its organisation.
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'quiz_id']);
            $table->index(['status', 'ends_at']);
        });

        DB::statement("ALTER TABLE live_sessions ADD CONSTRAINT live_sessions_status_check CHECK (status IN ('lobby', 'cancelled', 'running', 'finished'))");
        // SCOPE-022: at most one non-cancelled live session per quiz.
        DB::statement("CREATE UNIQUE INDEX live_sessions_one_per_quiz ON live_sessions (organization_id, quiz_id) WHERE status <> 'cancelled'");

        Schema::create('live_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('live_session_id');
            $table->foreignUuid('student_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('joined_at');
            $table->timestampTz('submitted_at')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->integer('final_rank')->nullable();
            $table->timestampsTz();

            $table->foreign(['organization_id', 'live_session_id'])
                ->references(['organization_id', 'id'])->on('live_sessions')
                ->cascadeOnDelete();

            // A student joins a session once; joining again continues it.
            $table->unique(['organization_id', 'live_session_id', 'student_id']);
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'student_id']);
        });

        Schema::create('live_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('live_participant_id');
            $table->uuid('quiz_question_id');
            $table->jsonb('response');
            $table->boolean('is_correct')->nullable();
            $table->decimal('points', 8, 2)->nullable();
            $table->timestampTz('answered_at');
            $table->timestampsTz();

            $table->foreign(['organization_id', 'live_participant_id'])
                ->references(['organization_id', 'id'])->on('live_participants')
                ->cascadeOnDelete();
            $table->foreign(['organization_id', 'quiz_question_id'])
                ->references(['organization_id', 'id'])->on('quiz_questions')
                ->restrictOnDelete();

            // The current answer of a participant to one question.
            $table->unique(['organization_id', 'live_participant_id', 'quiz_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_answers');
        Schema::dropIfExists('live_participants');
        Schema::dropIfExists('live_sessions');
    }
};

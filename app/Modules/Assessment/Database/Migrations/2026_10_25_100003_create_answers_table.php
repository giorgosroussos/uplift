<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ENT-Answer: the answer to one quiz question in an attempt, with its
| selections (ENT-AnswerSelection) and matches (ENT-AnswerMatch). Answers
| are never soft-deleted (DATA-007).
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('attempt_id');
            $table->uuid('quiz_question_id');
            $table->text('text_response')->nullable();
            $table->decimal('points_awarded', 8, 2)->nullable();
            $table->string('grading_status')->default('pending');
            $table->text('feedback')->nullable();
            $table->foreignUuid('graded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('graded_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['organization_id', 'attempt_id'])
                ->references(['organization_id', 'id'])->on('quiz_attempts')
                ->cascadeOnDelete();
            $table->foreign(['organization_id', 'quiz_question_id'])
                ->references(['organization_id', 'id'])->on('quiz_questions')
                ->restrictOnDelete();

            // One answer per question of an attempt.
            $table->unique(['organization_id', 'attempt_id', 'quiz_question_id']);
            $table->unique(['organization_id', 'id']);
        });

        DB::statement("ALTER TABLE answers ADD CONSTRAINT answers_grading_status_check CHECK (grading_status IN ('auto_graded', 'pending', 'graded'))");

        Schema::create('answer_selections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('answer_id');
            $table->foreignUuid('choice_option_id')->constrained('choice_options')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['organization_id', 'answer_id'])
                ->references(['organization_id', 'id'])->on('answers')
                ->cascadeOnDelete();

            $table->unique(['organization_id', 'answer_id', 'choice_option_id']);
        });

        Schema::create('answer_matches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('answer_id');
            $table->foreignUuid('left_pair_id')->constrained('matching_pairs')->restrictOnDelete();
            $table->foreignUuid('right_pair_id')->constrained('matching_pairs')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['organization_id', 'answer_id'])
                ->references(['organization_id', 'id'])->on('answers')
                ->cascadeOnDelete();

            // Each left item is matched at most once per answer.
            $table->unique(['organization_id', 'answer_id', 'left_pair_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_matches');
        Schema::dropIfExists('answer_selections');
        Schema::dropIfExists('answers');
    }
};

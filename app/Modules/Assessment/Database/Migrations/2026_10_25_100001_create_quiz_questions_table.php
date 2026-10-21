<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ENT-QuizQuestion: the ordered placement of a question in a quiz. The
| question is the quiz's own copy (in_bank = false, DATA-008), placed in
| exactly one quiz.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('quiz_id');
            $table->uuid('question_id');
            $table->unsignedSmallInteger('position');
            $table->timestampsTz();

            $table->foreign(['organization_id', 'quiz_id'])
                ->references(['organization_id', 'activity_id'])->on('quizzes')
                ->cascadeOnDelete();
            $table->foreign(['organization_id', 'question_id'])
                ->references(['organization_id', 'id'])->on('questions')
                ->cascadeOnDelete();

            // A quiz copy belongs to one quiz only.
            $table->unique(['organization_id', 'question_id']);
            // Lets answers reference a quiz question with its organisation.
            $table->unique(['organization_id', 'id']);
        });

        // One question per position of a quiz. Deferred, so a reorder can
        // swap positions inside one transaction.
        DB::statement('ALTER TABLE quiz_questions ADD CONSTRAINT quiz_questions_position_unique UNIQUE (organization_id, quiz_id, position) DEFERRABLE INITIALLY DEFERRED');
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};

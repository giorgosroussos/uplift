<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ENT-Quiz: the quiz subtype of ENT-Activity (Class Table Inheritance,
| DATA-001). The activity id is the primary key; organization_id is kept on
| the subtype too (DATA-007) and references the activity with it, so a quiz
| never points into another tenant.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->uuid('activity_id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('mode');
            // Seconds; null = no time limit (enforced on the server, DATA-004).
            $table->integer('time_limit')->nullable();
            $table->string('live_start_mode')->nullable();
            $table->timestampTz('live_scheduled_start_at')->nullable();
            $table->integer('max_attempts');
            $table->boolean('negative_marking');
            // Percentages from 0 to 100 as exact decimals (DATA-003).
            $table->decimal('negative_mark_fraction', 7, 4)->nullable();
            $table->decimal('pass_mark_percentage', 7, 4)->nullable();
            $table->string('score_visibility');
            $table->string('answers_visibility');
            $table->timestampsTz();

            $table->foreign(['organization_id', 'activity_id'])
                ->references(['organization_id', 'id'])->on('activities')
                ->cascadeOnDelete();

            // Lets child tables reference a quiz together with its organisation.
            $table->unique(['organization_id', 'activity_id']);
        });

        DB::statement("ALTER TABLE quizzes ADD CONSTRAINT quizzes_mode_check CHECK (mode IN ('self_paced', 'live'))");
        DB::statement("ALTER TABLE quizzes ADD CONSTRAINT quizzes_live_start_mode_check CHECK (live_start_mode IS NULL OR live_start_mode IN ('manual', 'scheduled'))");
        DB::statement("ALTER TABLE quizzes ADD CONSTRAINT quizzes_score_visibility_check CHECK (score_visibility IN ('immediate', 'after_deadline', 'hidden'))");
        DB::statement("ALTER TABLE quizzes ADD CONSTRAINT quizzes_answers_visibility_check CHECK (answers_visibility IN ('immediate', 'after_deadline', 'hidden'))");
        DB::statement('ALTER TABLE quizzes ADD CONSTRAINT quizzes_time_limit_check CHECK (time_limit IS NULL OR time_limit > 0)');
        DB::statement('ALTER TABLE quizzes ADD CONSTRAINT quizzes_max_attempts_check CHECK (max_attempts >= 1)');
        DB::statement('ALTER TABLE quizzes ADD CONSTRAINT quizzes_negative_mark_fraction_check CHECK ((NOT negative_marking AND negative_mark_fraction IS NULL) OR (negative_marking AND negative_mark_fraction > 0 AND negative_mark_fraction <= 100))');
        DB::statement('ALTER TABLE quizzes ADD CONSTRAINT quizzes_pass_mark_percentage_check CHECK (pass_mark_percentage IS NULL OR (pass_mark_percentage >= 0 AND pass_mark_percentage <= 100))');
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};

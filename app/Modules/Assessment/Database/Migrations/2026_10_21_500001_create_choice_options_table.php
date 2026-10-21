<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('choice_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('question_id');
            // For true/false questions the fixed texts 'true' and 'false' (DATA-016).
            $table->text('text');
            $table->boolean('is_correct');
            $table->unsignedSmallInteger('position');
            $table->timestampsTz();

            // Same organisation as the question; removed with it.
            $table->foreign(['organization_id', 'question_id'])
                ->references(['organization_id', 'id'])->on('questions')
                ->cascadeOnDelete();
        });

        // One option per position of a question. Deferred, so a reorder can
        // swap positions inside one transaction.
        DB::statement('ALTER TABLE choice_options ADD CONSTRAINT choice_options_position_unique UNIQUE (organization_id, question_id, position) DEFERRABLE INITIALLY DEFERRED');
    }

    public function down(): void
    {
        Schema::dropIfExists('choice_options');
    }
};

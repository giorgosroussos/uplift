<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_pairs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('question_id');
            $table->text('left_text');
            $table->text('right_text');
            $table->unsignedSmallInteger('position');
            $table->timestampsTz();

            // Same organisation as the question; removed with it.
            $table->foreign(['organization_id', 'question_id'])
                ->references(['organization_id', 'id'])->on('questions')
                ->cascadeOnDelete();
        });

        // One pair per position of a question. Deferred, so a reorder can
        // swap positions inside one transaction.
        DB::statement('ALTER TABLE matching_pairs ADD CONSTRAINT matching_pairs_position_unique UNIQUE (organization_id, question_id, position) DEFERRABLE INITIALLY DEFERRED');
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_pairs');
    }
};

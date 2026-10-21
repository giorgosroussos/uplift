<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            // The authoring teacher; a bank is personal (DATA-008, OWN-016).
            $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('type');
            $table->text('prompt');
            // DATA-003, DATA-016: exact decimal in (0, 100] with 2 decimal places.
            $table->decimal('points', 5, 2);
            // true: a bank question; false: a copy placed in a quiz (DATA-008).
            $table->boolean('in_bank');
            $table->timestampsTz();

            // Lets options and pairs reference a question together with its
            // organisation, so a child row can never point into another tenant.
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'owner_id', 'in_bank', 'created_at']);
        });

        DB::statement("ALTER TABLE questions ADD CONSTRAINT questions_type_check CHECK (type IN ('single_choice', 'multiple_choice', 'true_false', 'matching', 'free_text'))");
        DB::statement('ALTER TABLE questions ADD CONSTRAINT questions_points_check CHECK (points > 0 AND points <= 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};

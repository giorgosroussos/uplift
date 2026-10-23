<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| ENT-PollParticipation: records THAT a student voted, never what
| (DATA-006). One row per student and poll prevents a second vote, also
| under concurrent requests. Follows the student's erasure (SEC-003).
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_participations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('poll_id');
            $table->foreignUuid('student_id')->constrained('users')->cascadeOnDelete();
            $table->timestampsTz();

            $table->foreign(['organization_id', 'poll_id'])
                ->references(['organization_id', 'activity_id'])->on('polls')
                ->cascadeOnDelete();

            $table->unique(['organization_id', 'poll_id', 'student_id']);
            $table->index(['organization_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_participations');
    }
};

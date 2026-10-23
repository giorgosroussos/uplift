<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| ENT-PollVote: one chosen option of one vote. It carries no user
| reference (DATA-006) and no timestamps, so a vote can never be matched to
| a participation by time; its id is a random (not time-ordered) UUID for
| the same reason. Votes are never soft-deleted (DATA-007).
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('poll_option_id');

            $table->foreign(['organization_id', 'poll_option_id'])
                ->references(['organization_id', 'id'])->on('poll_options')
                ->cascadeOnDelete();

            $table->index(['organization_id', 'poll_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
    }
};

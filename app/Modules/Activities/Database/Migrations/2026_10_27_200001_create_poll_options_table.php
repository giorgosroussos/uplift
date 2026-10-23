<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ENT-PollOption: the ordered options of a poll; removed with the poll.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('poll_id');
            $table->string('label');
            $table->integer('position');
            $table->timestampsTz();

            $table->foreign(['organization_id', 'poll_id'])
                ->references(['organization_id', 'activity_id'])->on('polls')
                ->cascadeOnDelete();

            // Lets votes reference an option together with its organisation.
            $table->unique(['organization_id', 'id']);
        });

        // One option per position of a poll. Deferred, so a reorder can swap
        // positions inside one transaction.
        DB::statement('ALTER TABLE poll_options ADD CONSTRAINT poll_options_position_unique UNIQUE (organization_id, poll_id, position) DEFERRABLE INITIALLY DEFERRED');
        DB::statement('ALTER TABLE poll_options ADD CONSTRAINT poll_options_position_check CHECK (position >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_options');
    }
};

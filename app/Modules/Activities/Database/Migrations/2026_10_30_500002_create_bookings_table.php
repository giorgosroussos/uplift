<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ENT-Booking: the current booking of a student (individual activity) or a
| group (group activity); one per holder and activity, enforced by BookSlot
| under a lock of the activity row, which also keeps the bookings of a slot
| within its capacity (SCOPE-011, SCOPE-028). A change moves the row to the
| new slot, a cancellation deletes it. Follows the student's erasure
| (SEC-003). A group is only ever soft-deleted, so its bookings stay
| (SCOPE-025).
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('slot_id');
            $table->foreignUuid('student_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->uuid('group_id')->nullable();
            $table->timestampsTz();

            $table->foreign(['organization_id', 'slot_id'])
                ->references(['organization_id', 'id'])->on('slots')
                ->cascadeOnDelete();

            $table->foreign(['organization_id', 'group_id'])
                ->references(['organization_id', 'id'])->on('groups')
                ->cascadeOnDelete();

            $table->index(['organization_id', 'slot_id']);
            $table->index(['organization_id', 'student_id']);
            $table->index(['organization_id', 'group_id']);
        });

        // Held either by one student or by one group.
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_holder_check CHECK ((student_id IS NULL) <> (group_id IS NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

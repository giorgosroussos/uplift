<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('course_space_id');
            $table->string('name');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            // SCOPE-025: dissolved and empty groups are soft-deleted.
            $table->softDeletesTz();

            // Same organisation as the course space; removed with it.
            $table->foreign(['organization_id', 'course_space_id'])
                ->references(['organization_id', 'id'])->on('course_spaces')
                ->cascadeOnDelete();

            // Lets child tables reference a group together with its organisation.
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'course_space_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};

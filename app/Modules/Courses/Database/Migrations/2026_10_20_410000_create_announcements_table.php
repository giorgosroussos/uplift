<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('course_space_id');
            // The posting teacher; like a course space's owner, not deleted while referenced.
            $table->foreignUuid('author_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('body');
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            // DATA-007: announcements are soft-deleted.
            $table->softDeletesTz();

            // Same organisation as the course space; removed with it.
            $table->foreign(['organization_id', 'course_space_id'])
                ->references(['organization_id', 'id'])->on('course_spaces')
                ->cascadeOnDelete();

            $table->index(['organization_id', 'course_space_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};

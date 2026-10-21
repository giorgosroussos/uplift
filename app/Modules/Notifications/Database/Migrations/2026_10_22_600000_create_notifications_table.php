<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ENT-Notification (UC-007, DEC-002): a user's in-app notification.
        // data holds ids and title snapshots only, never personal data.
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 255);
            $table->jsonb('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'user_id', 'created_at']);
            $table->index('created_at');
        });

        // ENT-NotificationPreference (DEC-002): one per user; a user without
        // a row receives DEC-001 notification emails.
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('email_enabled');
            $table->timestampsTz();

            $table->unique(['organization_id', 'user_id']);
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};

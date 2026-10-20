<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('role');
            $table->string('name');
            $table->string('nickname');
            $table->string('avatar')->nullable();
            $table->string('email', 254);
            // Nullable: SSO-only accounts have no password (EXT-001).
            $table->string('password')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->boolean('is_minor')->nullable();
            $table->timestampTz('email_verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('locale')->nullable();
            $table->rememberToken();
            $table->timestampsTz();

            $table->unique(['organization_id', 'email']);
            $table->unique(['organization_id', 'nickname']);
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('student', 'teacher', 'org_admin'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_locale_check CHECK (locale IS NULL OR locale IN ('el', 'en'))");
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_lowercase_check CHECK (email = lower(email))');

        // Reset tokens are per organisation: the same email may belong to
        // users of different organisations (DATA-007).
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('email', 254);
            $table->string('token');
            $table->timestampTz('created_at')->nullable();

            $table->unique(['organization_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};

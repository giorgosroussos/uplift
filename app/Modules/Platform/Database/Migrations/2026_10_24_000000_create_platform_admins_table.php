<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ENT-PlatformAdmin: platform super-admins live outside tenancy, in their own
| table (never in users) with their own guard (SEC-006). TOTP MFA is required
| (SEC-011): the secret and recovery codes are stored encrypted.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('email', 254)->unique();
            $table->string('password', 255);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();
            $table->timestampsTz();
        });

        // Emails are compared case-insensitively: stored lower-cased.
        DB::statement('ALTER TABLE platform_admins ADD CONSTRAINT platform_admins_email_lowercase_check CHECK (email = lower(email))');
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_admins');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| UC-006: optional TOTP MFA for org admins (SEC-012) and the profile rules of
| DATA-014: nicknames unique within the organisation ignoring case, avatars
| only from the bundled set (avatar-01 to avatar-12).
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Encrypted by Fortify before they are stored (sensitive).
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();

            $table->dropUnique(['organization_id', 'nickname']);
        });

        DB::statement('CREATE UNIQUE INDEX users_organization_id_nickname_ci_unique ON users (organization_id, lower(nickname))');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_avatar_check CHECK (avatar IS NULL OR avatar ~ '^avatar-(0[1-9]|1[0-2])$')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_avatar_check');
        DB::statement('DROP INDEX IF EXISTS users_organization_id_nickname_ci_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['organization_id', 'nickname']);
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};

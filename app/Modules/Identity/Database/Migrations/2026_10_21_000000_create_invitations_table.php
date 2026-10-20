<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ENT-Invitation (UC-005, UC-032). token_hash holds the encrypted
        // hash of the single-use token; the token itself is never stored.
        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('email', 254);
            $table->string('role');
            $table->string('token_hash', 255);
            $table->foreignUuid('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('expires_at');
            $table->string('status');
            $table->timestampsTz();

            $table->index(['organization_id', 'email']);
            $table->index(['organization_id', 'status']);
        });

        DB::statement("ALTER TABLE invitations ADD CONSTRAINT invitations_role_check CHECK (role IN ('student', 'teacher', 'org_admin'))");
        DB::statement("ALTER TABLE invitations ADD CONSTRAINT invitations_status_check CHECK (status IN ('pending', 'accepted', 'revoked', 'expired'))");
        DB::statement('ALTER TABLE invitations ADD CONSTRAINT invitations_email_lowercase_check CHECK (email = lower(email))');
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};

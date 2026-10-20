<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ENT-AuditEntry: append-only record of security-relevant actions (SEC-010).
| organization_id is nullable (platform-level entries have no organisation).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->string('actor_type');
            $table->uuid('actor_id')->nullable();
            $table->string('action', 255);
            $table->string('subject_type', 255);
            $table->uuid('subject_id');
            $table->jsonb('changes')->nullable();
            $table->timestampTz('occurred_at');

            $table->index(['organization_id', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('occurred_at');
        });

        DB::statement("ALTER TABLE audit_entries ADD CONSTRAINT audit_entries_actor_type_check CHECK (actor_type IN ('user', 'platform_admin', 'system'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_entries');
    }
};

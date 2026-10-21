<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| ENT-AuditEntry: audit_entries.organization_id -> organizations.id
| (on delete restrict). Stays nullable for platform-level entries.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_entries', function (Blueprint $table) {
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_entries', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATA-015: set when the invitation email failed after its last
        // retry (INT-001); cleared when the invitation is resent.
        Schema::table('invitations', function (Blueprint $table) {
            $table->timestampTz('email_failed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn('email_failed_at');
        });
    }
};

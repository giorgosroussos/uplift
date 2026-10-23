<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| SCOPE-012, SCOPE-025: a group attempt belongs to a group of the same
| organisation. Groups are only soft-deleted, so the attempt keeps its
| group as history (restrict).
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->foreign(['organization_id', 'group_id'])
                ->references(['organization_id', 'id'])->on('groups')
                ->restrictOnDelete();
            $table->index(['organization_id', 'quiz_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'quiz_id', 'group_id']);
            $table->dropForeign(['organization_id', 'group_id']);
        });
    }
};

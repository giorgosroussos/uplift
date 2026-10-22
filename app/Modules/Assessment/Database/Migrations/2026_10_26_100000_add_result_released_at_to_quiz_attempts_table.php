<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| DEC-003: ENT-QuizAttempt.result_released_at records, once, when the final
| score became visible to the student (UTC). The release command looks for
| graded attempts that are not released yet.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->timestampTz('result_released_at')->nullable();
            $table->index(['status', 'result_released_at']);
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropIndex(['status', 'result_released_at']);
            $table->dropColumn('result_released_at');
        });
    }
};

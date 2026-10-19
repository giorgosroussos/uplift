<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('subdomain')->unique();
            $table->string('status');
            $table->string('logo_path')->nullable();
            $table->string('accent_color')->nullable();
            $table->string('student_term_el');
            $table->string('student_term_en');
            $table->string('teacher_term_el');
            $table->string('teacher_term_en');
            $table->string('grading_scale');
            $table->boolean('leaderboard_enabled');
            $table->string('default_locale');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_status_check CHECK (status IN ('active', 'suspended'))");
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_grading_scale_check CHECK (grading_scale IN ('scale_0_10', 'scale_0_100', 'points_pass_mark'))");
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_default_locale_check CHECK (default_locale IN ('el', 'en'))");
        DB::statement('ALTER TABLE organizations ADD CONSTRAINT organizations_subdomain_lowercase_check CHECK (subdomain = lower(subdomain))');
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};

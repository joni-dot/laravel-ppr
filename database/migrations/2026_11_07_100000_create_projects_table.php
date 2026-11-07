<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 10);
            $table->text('description')->nullable();
            $table->smallInteger('risk_amber_days');
            $table->smallInteger('risk_red_days');
            $table->string('number_prefix', 10)->nullable();
            $table->boolean('number_includes_project_code')->default(true);
            $table->boolean('number_includes_category')->default(true);
            $table->string('number_separator', 20)->default('hyphen');
            $table->smallInteger('number_padding')->default(4);
            $table->integer('number_start')->default(1);
            $table->string('sub_number_separator', 20)->default('dot');
            $table->smallInteger('sub_number_padding')->default(2);
            $table->timestamps();
        });

        // Codes are part of package numbers, so "p100" and "P100" must not both exist.
        DB::statement('CREATE UNIQUE INDEX projects_code_unique ON projects (lower(code))');
        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_risk_days_check CHECK (risk_amber_days > risk_red_days)');
        DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_number_separator_check CHECK (number_separator IN ('hyphen', 'dot', 'slash', 'underscore', 'none'))");
        DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_sub_number_separator_check CHECK (sub_number_separator IN ('hyphen', 'dot', 'slash', 'underscore'))");
        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_number_padding_check CHECK (number_padding BETWEEN 1 AND 8 AND sub_number_padding BETWEEN 1 AND 4)');
        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_number_start_check CHECK (number_start BETWEEN 1 AND 999999)');

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 20);
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            // Finds the projects a person belongs to, which every project list filters on.
            $table->index(['user_id', 'project_id']);
        });

        DB::statement("ALTER TABLE project_members ADD CONSTRAINT project_members_role_check CHECK (role IN ('project_manager', 'buyer', 'package_owner', 'viewer'))");

        Schema::create('project_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name', 100);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX project_categories_code_unique ON project_categories (project_id, lower(code))');
    }

    public function down(): void
    {
        Schema::dropIfExists('project_categories');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
